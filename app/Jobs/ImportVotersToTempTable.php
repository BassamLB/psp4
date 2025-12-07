<?php

namespace App\Jobs;

use App\Events\VoterUploadProgress;
use App\Models\VoterImportTemp;
use App\Models\VoterUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportVotersToTempTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour

    public $tries = 3;

    public $maxExceptions = 3;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(public VoterUpload $upload, public array $options = [])
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $meta = $this->upload->meta ?? [];
        $cleaned = $meta['cleaned_path'] ?? null;

        if (! $cleaned) {
            Log::error('ImportVotersToTempTable: cleaned_path not found in upload meta', ['upload_id' => $this->upload->id]);
            $this->upload->status = 'error';
            $this->upload->meta = array_merge($meta, ['import_error' => 'cleaned_path_missing']);
            $this->upload->save();

            return;
        }

        // Resolve the cleaned CSV path deterministically under storage/app/private/imports
        $filename = basename(preg_replace('#^app/#', '', $cleaned));
        $full = storage_path('app/private/imports/'.$filename);

        if (! is_readable($full)) {
            Log::error('ImportVotersToTempTable: cleaned file not readable', ['path' => $full]);
            $this->upload->status = 'error';
            $this->upload->meta = array_merge($meta, ['import_error' => 'cleaned_unreadable', 'cleaned_candidate' => $full]);
            $this->upload->save();

            return;
        }

        // Create failed rows CSV file
        $failedRowsFile = storage_path('app/private/imports/'.pathinfo($filename, PATHINFO_FILENAME).'_failed_rows.csv');
        $failedRowsFh = fopen($failedRowsFile, 'w');
        $failedRowsHeader = ['row_number', 'error', 'sijil_number', 'town_id', 'first_name', 'family_name', 'father_name', 'mother_full_name', 'gender_id', 'sect_id', 'personal_sect', 'date_of_birth'];
        fputcsv($failedRowsFh, $failedRowsHeader);

        // Check if voter_imports_temp has data and truncate if not empty
        $tempCount = DB::table('voter_imports_temp')->count();
        if ($tempCount > 0) {
            Log::info('ImportVotersToTempTable: Truncating voter_imports_temp table', ['existing_rows' => $tempCount, 'upload_id' => $this->upload->id]);
            try {
                DB::table('voter_imports_temp')->truncate();
            } catch (\Throwable $e) {
                Log::error('ImportVotersToTempTable: Failed to truncate voter_imports_temp', ['exception' => $e->getMessage()]);
                $this->upload->status = 'error';
                $this->upload->meta = array_merge($meta, ['import_error' => 'truncate_failed', 'truncate_exception' => $e->getMessage()]);
                $this->upload->save();

                return;
            }
        }

        // mark importing
        $this->upload->status = 'importing-temp';
        $this->upload->save();

        // Broadcast initial progress
        try {
            event(new VoterUploadProgress($this->upload, ['inserted' => 0, 'percent' => 0, 'phase' => 'starting']));
        } catch (\Throwable $e) {
            Log::debug('Initial progress broadcast failed', ['exception' => $e->getMessage()]);
        }

        $batch = (int) ($this->options['batch'] ?? 1000);
        // How many rows to insert in a single bulk insert. Keep reasonable to avoid
        // excessive memory use; 1000 is a good default for medium-sized CSVs.
        $insertChunk = max(250, min(5000, $batch));

        // timing meters
        $startTime = microtime(true);
        $chunkMeters = [];
        $lastMeterSave = $startTime;
        $maxChunkMeters = 100; // Limit array size to prevent memory issues
        $now = now(); // Reuse timestamp for all rows in this run

        $fh = new \SplFileObject($full, 'r');
        $fh->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        // Estimate total rows more efficiently
        $totalLines = null;
        try {
            $fileSize = filesize($full);
            if ($fileSize !== false && $fileSize > 0) {
                // Sample first 1000 bytes to estimate average line length
                $sample = file_get_contents($full, false, null, 0, min(1000, $fileSize));
                if ($sample !== false) {
                    $sampleLines = substr_count($sample, "\n");
                    if ($sampleLines > 0) {
                        $avgLineLength = strlen($sample) / $sampleLines;
                        $totalLines = (int) ($fileSize / $avgLineLength);
                    }
                }
            }
        } catch (\Throwable $e) {
            $totalLines = null;
        }

        $header = null;
        $rowsInserted = 0;
        $insertBuffer = [];
        $rowsProcessed = 0;

        foreach ($fh as $row) {
            if (! $row) {
                continue;
            }
            // SplFileObject returns array even for blank lines
            if ($header === null) {
                $header = $row;

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            // normalize row to header
            $data = [];
            if (is_array($header)) {
                foreach ($header as $i => $col) {
                    $data[$col ?? $i] = $row[$i] ?? null;
                }
            }

            $insertBuffer[] = [
                'sijil_number' => $data['sijil_number'] ?? null,
                'town_id' => $data['town_id'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'family_name' => $data['family_name'] ?? null,
                'father_name' => $data['father_name'] ?? null,
                'mother_full_name' => $data['mother_full_name'] ?? null,
                'gender_id' => $data['gender_id'] ?? null,
                'sect_id' => $data['sect_id'] ?? null,
                'personal_sect' => $data['personal_sect'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'sijil_additional_string' => $data['sijil_additional_string'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rowsProcessed++;

            // Flush buffer in chunks to perform bulk inserts instead of per-row Eloquent
            if (count($insertBuffer) >= $insertChunk) {
                $chunkStart = microtime(true);
                $chunkCount = count($insertBuffer);
                $actualInserted = 0;
                $failedRows = 0;

                try {
                    DB::table('voter_imports_temp')->insert($insertBuffer);
                    $actualInserted = $chunkCount;
                } catch (\Throwable $e) {
                    Log::warning('Bulk insert into voter_imports_temp failed, trying individual inserts', [
                        'exception' => $e->getMessage(),
                        'chunk_size' => $chunkCount,
                        'upload_id' => $this->upload->id,
                    ]);

                    // fallback to inserting rows one-by-one to salvage progress
                    foreach ($insertBuffer as $rowData) {
                        try {
                            VoterImportTemp::create($rowData);
                            $actualInserted++;
                        } catch (\Throwable $insertError) {
                            $failedRows++;

                            // Write failed row to CSV file
                            fputcsv($failedRowsFh, [
                                $rowsProcessed - $chunkCount + $failedRows, // approximate row number
                                $insertError->getMessage(),
                                $rowData['sijil_number'] ?? '',
                                $rowData['town_id'] ?? '',
                                $rowData['first_name'] ?? '',
                                $rowData['family_name'] ?? '',
                                $rowData['father_name'] ?? '',
                                $rowData['mother_full_name'] ?? '',
                                $rowData['gender_id'] ?? '',
                                $rowData['sect_id'] ?? '',
                                $rowData['personal_sect'] ?? '',
                                $rowData['date_of_birth'] ?? '',
                            ]);

                            Log::error('Individual row insert failed', [
                                'error' => $insertError->getMessage(),
                                'sijil' => $rowData['sijil_number'] ?? null,
                                'town_id' => $rowData['town_id'] ?? null,
                                'first_name' => $rowData['first_name'] ?? null,
                                'upload_id' => $this->upload->id,
                            ]);
                        }
                    }

                    if ($failedRows > 0) {
                        Log::warning('Some rows failed to insert', [
                            'failed_count' => $failedRows,
                            'successful_count' => $actualInserted,
                            'upload_id' => $this->upload->id,
                        ]);
                    }
                }

                $chunkDuration = microtime(true) - $chunkStart;
                $rowsInserted += $actualInserted;
                $insertBuffer = [];

                // record meter for this chunk (limit array size)
                $rps = $chunkDuration > 0 ? round($chunkCount / $chunkDuration, 2) : null;
                if (count($chunkMeters) < $maxChunkMeters) {
                    $chunkMeters[] = ['rows' => $chunkCount, 'seconds' => round($chunkDuration, 3), 'rps' => $rps];
                } else {
                    // Keep only the most recent metrics by removing oldest
                    array_shift($chunkMeters);
                    $chunkMeters[] = ['rows' => $chunkCount, 'seconds' => round($chunkDuration, 3), 'rps' => $rps];
                }

                // Only log every 50,000 rows to reduce I/O overhead
                if ($rowsInserted % 50000 === 0 || $rowsInserted < 50000) {
                    Log::info('ImportVotersToTempTable: progress', ['inserted' => $rowsInserted, 'rps' => $rps, 'upload_id' => $this->upload->id]);
                }

                // occasionally persist timing metrics to upload meta (every ~10s)
                if (microtime(true) - $lastMeterSave > 10) {
                    try {
                        $elapsed = microtime(true) - $startTime;
                        $avg = $elapsed > 0 ? round($rowsInserted / $elapsed, 2) : null;
                        $this->upload->meta = array_merge($this->upload->meta ?? [], ['import_metrics' => ['chunks' => $chunkMeters, 'inserted' => $rowsInserted, 'elapsed' => round($elapsed, 3), 'avg_rps' => $avg]]);
                        $this->upload->save();
                    } catch (\Throwable $_) {
                        // ignore
                    }
                    $lastMeterSave = microtime(true);
                }
            }

            // allow other jobs to run occasionally and emit progress periodically
            if (($rowsProcessed % 1000) === 0) {
                try {
                    $totalRows = $totalLines ? max(0, $totalLines - 1) : null;
                    $percent = ($totalRows && $totalRows > 0) ? min(99, (int) floor(($rowsProcessed / $totalRows) * 100)) : null;
                    event(new VoterUploadProgress($this->upload, ['inserted' => $rowsInserted, 'processed' => $rowsProcessed, 'total' => $totalRows, 'percent' => $percent, 'phase' => 'importing']));
                } catch (\Throwable $e) {
                    Log::debug('Progress broadcast failed during import temp', ['exception' => $e->getMessage()]);
                }
            }
        }

        // insert any remaining buffered rows
        if (! empty($insertBuffer)) {
            $chunkStart = microtime(true);
            $chunkCount = count($insertBuffer);
            $actualInserted = 0;
            $failedRows = 0;

            try {
                DB::table('voter_imports_temp')->insert($insertBuffer);
                $actualInserted = $chunkCount;
            } catch (\Throwable $e) {
                Log::warning('Final bulk insert into voter_imports_temp failed, trying individual inserts', [
                    'exception' => $e->getMessage(),
                    'chunk_size' => $chunkCount,
                    'upload_id' => $this->upload->id,
                ]);

                foreach ($insertBuffer as $rowData) {
                    try {
                        VoterImportTemp::create($rowData);
                        $actualInserted++;
                    } catch (\Throwable $insertError) {
                        $failedRows++;

                        // Write failed row to CSV file
                        fputcsv($failedRowsFh, [
                            $rowsProcessed - $chunkCount + $failedRows, // approximate row number
                            $insertError->getMessage(),
                            $rowData['sijil_number'] ?? '',
                            $rowData['town_id'] ?? '',
                            $rowData['first_name'] ?? '',
                            $rowData['family_name'] ?? '',
                            $rowData['father_name'] ?? '',
                            $rowData['mother_full_name'] ?? '',
                            $rowData['gender_id'] ?? '',
                            $rowData['sect_id'] ?? '',
                            $rowData['personal_sect'] ?? '',
                            $rowData['date_of_birth'] ?? '',
                        ]);

                        Log::error('Individual row insert failed in final chunk', [
                            'error' => $insertError->getMessage(),
                            'sijil' => $rowData['sijil_number'] ?? null,
                            'town_id' => $rowData['town_id'] ?? null,
                            'first_name' => $rowData['first_name'] ?? null,
                            'upload_id' => $this->upload->id,
                        ]);
                    }
                }

                if ($failedRows > 0) {
                    Log::warning('Some rows failed in final chunk', [
                        'failed_count' => $failedRows,
                        'successful_count' => $actualInserted,
                        'upload_id' => $this->upload->id,
                    ]);
                }
            }

            $rowsInserted += $actualInserted;
            $chunkDuration = microtime(true) - $chunkStart;
            $rps = $chunkDuration > 0 ? round($actualInserted / $chunkDuration, 2) : null;
            if (count($chunkMeters) < $maxChunkMeters) {
                $chunkMeters[] = ['rows' => $chunkCount, 'seconds' => round($chunkDuration, 3), 'rps' => $rps];
            } else {
                array_shift($chunkMeters);
                $chunkMeters[] = ['rows' => $chunkCount, 'seconds' => round($chunkDuration, 3), 'rps' => $rps];
            }
            Log::info('ImportVotersToTempTable: final chunk inserted', ['rows' => $chunkCount, 'seconds' => $chunkDuration, 'rps' => $rps, 'upload_id' => $this->upload->id]);
        }

        // final timing summary
        $totalElapsed = microtime(true) - $startTime;
        $avgRps = $totalElapsed > 0 ? round($rowsInserted / $totalElapsed, 2) : null;

        // Get actual count from database to verify
        $actualDbCount = DB::table('voter_imports_temp')->count();

        // Close failed rows file
        fclose($failedRowsFh);

        // Count failed rows by reading the CSV (excluding header)
        $failedRowsCount = 0;
        if (file_exists($failedRowsFile)) {
            $failedRowsCount = max(0, count(file($failedRowsFile)) - 1);
        }

        // Delete the failed rows file if it's empty (no failures)
        if ($failedRowsCount === 0 && file_exists($failedRowsFile)) {
            unlink($failedRowsFile);
            $failedRowsFile = null;
        }

        Log::info('ImportVotersToTempTable completed', [
            'upload_id' => $this->upload->id,
            'rows_processed' => $rowsProcessed,
            'rows_inserted_counted' => $rowsInserted,
            'rows_in_db' => $actualDbCount,
            'discrepancy' => $rowsProcessed - $actualDbCount,
            'failed_rows_count' => $failedRowsCount,
            'failed_rows_file' => $failedRowsFile ? basename($failedRowsFile) : null,
            'elapsed_seconds' => round($totalElapsed, 2),
            'avg_rps' => $avgRps,
        ]);

        try {
            $this->upload->meta = array_merge($this->upload->meta ?? [], ['import_metrics' => ['chunks' => $chunkMeters, 'inserted' => $rowsInserted, 'elapsed' => round($totalElapsed, 3), 'avg_rps' => $avgRps]]);
            $this->upload->save();
        } catch (\Throwable $_) {
            // ignore
        }

        // Update upload meta with temp count (merge with current meta so we don't clobber import_metrics)
        $metaUpdates = ['temp_rows' => $actualDbCount];
        if ($failedRowsFile && $failedRowsCount > 0) {
            $metaUpdates['failed_rows_file'] = 'private/imports/'.basename($failedRowsFile);
            $metaUpdates['failed_rows_count'] = $failedRowsCount;
        }
        $this->upload->meta = array_merge($this->upload->meta ?? [], $metaUpdates);
        $this->upload->status = 'temp_imported';
        $this->upload->save();

        // Broadcast final progress and dispatch merge job
        try {
            $totalRows = $totalLines ? max(0, $totalLines - 1) : null;
            event(new VoterUploadProgress($this->upload, [
                'inserted' => $rowsInserted,
                'processed' => $rowsProcessed,
                'total' => $totalRows,
                'percent' => 100,
                'phase' => 'temp_imported',
                'message' => 'Imported to temporary table, starting merge',
            ]));
        } catch (\Throwable $e) {
            Log::debug('Failed to broadcast temp import completion', ['upload_id' => $this->upload->id, 'exception' => $e->getMessage()]);
        }

        // Dispatch optimized merge job (chained) — merge will respect dry_run option
        MergeVotersFromTempOptimized::dispatch($this->upload, $this->options)->onQueue('imports');
    }
}
