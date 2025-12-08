<?php

namespace App\Jobs;

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

    public int $timeout = 3600;

    public int $tries = 3;

    public int $maxExceptions = 3;

    private const STORAGE_DIR = 'private/imports';

    private const DEFAULT_BATCH_SIZE = 1000;

    private const MAX_INSERT_CHUNK = 1000;

    private const MIN_INSERT_CHUNK = 250;

    private const MAX_CHUNK_METERS = 20;

    private const MEMORY_LOG_INTERVAL = 25000;

    private const PROGRESS_LOG_INTERVAL = 50000;

    private const METER_SAVE_INTERVAL = 10;

    public function __construct(public VoterUpload $upload, public array $options = [])
    {
        $this->onQueue('imports');
        $this->onConnection('redis');
    }

    public function handle(): void
    {
        Log::info('ImportVotersToTempTable: Starting', [
            'upload_id' => $this->upload->id,
            'options' => $this->options,
        ]);

        try {
            $inputPath = $this->resolveInputPath();

            if (! $inputPath) {
                $this->handleInputNotFound();

                return;
            }

            $this->updateStatus('importing-temp');
            $this->prepareTempTable();

            $failedRowsFile = $this->initializeFailedRowsFile($inputPath);
            $result = $this->processImport($inputPath, $failedRowsFile);

            $this->handleImportResult($result, $failedRowsFile);
        } catch (\Throwable $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Resolve input file path from upload meta
     */
    protected function resolveInputPath(): ?string
    {
        $meta = $this->upload->meta ?? [];
        $cleaned = $meta['cleaned_path'] ?? null;

        if (! $cleaned) {
            Log::error('ImportVotersToTempTable: cleaned_path not found in upload meta', [
                'upload_id' => $this->upload->id,
            ]);

            return null;
        }

        $filename = basename(preg_replace('#^app/#', '', $cleaned));
        $fullPath = storage_path('app/' . self::STORAGE_DIR . '/' . $filename);

        if (! is_readable($fullPath)) {
            Log::error('ImportVotersToTempTable: cleaned file not readable', [
                'path' => $fullPath,
                'upload_id' => $this->upload->id,
            ]);

            return null;
        }

        Log::info('ImportVotersToTempTable: Input file resolved', [
            'path' => $fullPath,
            'upload_id' => $this->upload->id,
        ]);

        return $fullPath;
    }

    /**
     * Handle case when input file is not found
     */
    protected function handleInputNotFound(): void
    {
        $meta = $this->upload->meta ?? [];

        $this->upload->status = 'error';
        $this->upload->meta = array_merge($meta, [
            'import_error' => 'cleaned_path_missing_or_unreadable',
            'timestamp' => now()->toIso8601String(),
        ]);
        $this->upload->save();
    }

    /**
     * Update upload status
     */
    protected function updateStatus(string $status): void
    {
        $this->upload->status = $status;
        $this->upload->save();
    }

    /**
     * Prepare temp table by truncating if needed
     */
    protected function prepareTempTable(): void
    {
        $tempCount = DB::table('voter_imports_temp')->count();

        if ($tempCount > 0) {
            Log::info('ImportVotersToTempTable: Truncating voter_imports_temp table', [
                'existing_rows' => $tempCount,
                'upload_id' => $this->upload->id,
            ]);

            try {
                DB::table('voter_imports_temp')->truncate();
            } catch (\Throwable $e) {
                Log::error('ImportVotersToTempTable: Failed to truncate voter_imports_temp', [
                    'exception' => $e->getMessage(),
                    'upload_id' => $this->upload->id,
                ]);
                throw new \Exception('Failed to prepare temp table: ' . $e->getMessage());
            }
        }
    }

    /**
     * Initialize failed rows CSV file
     */
    protected function initializeFailedRowsFile(string $inputPath): string
    {
        $filename = basename($inputPath);
        $failedRowsFile = storage_path('app/' . self::STORAGE_DIR . '/' . pathinfo($filename, PATHINFO_FILENAME) . '_failed_rows.csv');

        $failedRowsFh = fopen($failedRowsFile, 'w');
        if (! $failedRowsFh) {
            throw new \Exception("Could not create failed rows file: {$failedRowsFile}");
        }

        $failedRowsHeader = [
            'row_number',
            'error',
            'sijil_number',
            'town_id',
            'first_name',
            'family_name',
            'father_name',
            'mother_full_name',
            'gender_id',
            'sect_id',
            'personal_sect',
            'date_of_birth',
        ];

        fputcsv($failedRowsFh, $failedRowsHeader);
        fclose($failedRowsFh);

        return $failedRowsFile;
    }

    /**
     * Process the CSV import
     */
    protected function processImport(string $inputPath, string $failedRowsFile): array
    {
        $startTime = microtime(true);
        $chunkMeters = [];
        $lastMeterSave = $startTime;

        $batchSize = (int) ($this->options['batch'] ?? self::DEFAULT_BATCH_SIZE);
        $insertChunk = max(self::MIN_INSERT_CHUNK, min(self::MAX_INSERT_CHUNK, $batchSize));

        $this->logMemoryUsage('Starting import');

        $now = now();
        $fh = new \SplFileObject($inputPath, 'r');
        $fh->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

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
                            $failedRowsFh = fopen($failedRowsFile, 'a');
                            if ($failedRowsFh) {
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
                                fclose($failedRowsFh);
                            }

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

                // Clear memory and log usage periodically
                if ($rowsInserted % 25000 === 0) {
                    gc_collect_cycles();
                    Log::info('ImportVotersToTempTable: Memory check', [
                        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                        'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                        'rows_inserted' => $rowsInserted,
                        'upload_id' => $this->upload->id,
                    ]);
                }

                // record meter for this chunk (limit array size)
                $rps = $chunkDuration > 0 ? round($chunkCount / $chunkDuration, 2) : null;
                if (count($chunkMeters) < self::MAX_CHUNK_METERS) {
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
                        $failedRowsFh = fopen($failedRowsFile, 'a');
                        if ($failedRowsFh) {
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
                            fclose($failedRowsFh);
                        }

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
            if (count($chunkMeters) < self::MAX_CHUNK_METERS) {
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

        return [
            'rows_processed' => $rowsProcessed,
            'rows_inserted' => $rowsInserted,
            'rows_in_db' => $actualDbCount,
            'failed_rows_count' => $failedRowsCount,
            'elapsed_seconds' => round($totalElapsed, 2),
            'avg_rps' => $avgRps,
            'chunk_metrics' => $chunkMeters,
        ];
    }

    /**
     * Log memory usage with context
     */
    protected function logMemoryUsage(string $context): void
    {
        Log::info("ImportVotersToTempTable: {$context}", [
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'upload_id' => $this->upload->id,
        ]);

        // Trigger garbage collection to free memory
        gc_collect_cycles();
    }

    /**
     * Handle import result and finalize upload
     */
    protected function handleImportResult(array $result, string $failedRowsFile): void
    {
        $this->logMemoryUsage('Import completed');

        Log::info('ImportVotersToTempTable: Completed', array_merge($result, [
            'upload_id' => $this->upload->id,
        ]));

        // Clean up failed rows file if empty
        $failedRowsPath = null;
        if ($result['failed_rows_count'] > 0 && file_exists($failedRowsFile)) {
            $failedRowsPath = 'private/imports/' . basename($failedRowsFile);
        } elseif (file_exists($failedRowsFile)) {
            unlink($failedRowsFile);
        }

        // Update upload meta with results
        $metaUpdates = [
            'temp_rows' => $result['rows_in_db'],
            'import_metrics' => [
                'chunks' => $result['chunk_metrics'],
                'inserted' => $result['rows_inserted'],
                'elapsed' => $result['elapsed_seconds'],
                'avg_rps' => $result['avg_rps'],
            ],
        ];

        if ($failedRowsPath && $result['failed_rows_count'] > 0) {
            $metaUpdates['failed_rows_file'] = $failedRowsPath;
            $metaUpdates['failed_rows_count'] = $result['failed_rows_count'];
        }

        $this->upload->meta = array_merge($this->upload->meta ?? [], $metaUpdates);
        $this->upload->status = 'temp_imported';
        $this->upload->save();

        $this->chainNextJob();
    }

    /**
     * Chain next job in the pipeline
     */
    protected function chainNextJob(): void
    {
        try {
            Log::info('ImportVotersToTempTable: About to dispatch merge job', [
                'upload_id' => $this->upload->id,
            ]);

            MergeVotersFromTempOptimized::dispatch($this->upload, $this->options)
                ->onConnection('redis')  // Explicitly set connection
                ->onQueue('imports');

            Log::info('ImportVotersToTempTable: Merge job dispatched successfully', [
                'upload_id' => $this->upload->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportVotersToTempTable: Failed to chain next job', [
                'upload_id' => $this->upload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle job failure
     */
    protected function handleFailure(\Throwable $e): void
    {
        Log::error('ImportVotersToTempTable: Job failed', [
            'upload_id' => $this->upload->id,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        try {
            $this->upload->status = 'import_failed';
            $this->upload->meta = array_merge($this->upload->meta ?? [], [
                'error' => 'import_exception',
                'exception_message' => $e->getMessage(),
                'exception_type' => get_class($e),
                'failed_at' => now()->toIso8601String(),
            ]);
            $this->upload->save();
        } catch (\Throwable $savingError) {
            Log::error('ImportVotersToTempTable: Failed to save error state', [
                'upload_id' => $this->upload->id,
                'error' => $savingError->getMessage(),
            ]);
        }

        throw $e;
    }

    /**
     * Handle permanently failed job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ImportVotersToTempTable: Job failed permanently', [
            'upload_id' => $this->upload->id,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $this->upload->status = 'import_failed';
            $this->upload->meta = array_merge($this->upload->meta ?? [], [
                'error' => 'job_failed_permanently',
                'exception' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ]);
            $this->upload->save();
        } catch (\Throwable $e) {
            Log::error('ImportVotersToTempTable: Failed to handle job failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get job tags for monitoring
     */
    public function tags(): array
    {
        return [
            'importing',
            'upload:' . $this->upload->id,
        ];
    }
}
