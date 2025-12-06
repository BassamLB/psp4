<?php

namespace App\Console\Commands;

use App\Helpers\VoterImportHelper;
use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Using SplFileObject for streaming CSV processing

class ImportVoters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voters:import 
                            {file : Path to CSV file}
                            {--batch=250 : Number of records to process per batch}
                            {--skip-header : Skip first row if it contains headers}
                            {--dry-run : Preview import without saving}
                            {--show-unmatched : Show unmatched reference data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import voters from CSV file with intelligent matching';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $batchSize = (int) $this->option('batch');
        $skipHeader = $this->option('skip-header');
        $dryRun = $this->option('dry-run');
        $showUnmatched = $this->option('show-unmatched');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return 1;
        }

        $this->info("🚀 Starting voter import from: {$filePath}");
        $this->info("Batch size: {$batchSize}");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No data will be saved');
        }

        // streaming handled by SplFileObject

        try {
            // Initialize caches for performance
            $this->info('📦 Loading reference data into cache...');
            VoterImportHelper::initializeCaches();

            // Stream the CSV using SplFileObject to keep memory usage bounded.
            $file = new \SplFileObject($filePath, 'r');
            $file->setCsvControl(',');
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

            $headers = null;
            if ($skipHeader) {
                // Read header row once and use it to build associative records
                $headers = $file->fgetcsv();
                if ($headers === false) {
                    throw new \RuntimeException("Unable to read header row from: {$filePath}");
                }
                if (! is_array($headers)) {
                    $headers = null;
                } else {
                    // Normalize header names (trim)
                    $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, $headers);
                }
            }

            $totalRows = 0;
            $successCount = 0;
            $errorCount = 0;
            $batch = [];
            $errors = [];
            // Use associative sets for unmatched values to avoid storing duplicates
            // and cap the number of unique values per field to limit memory usage.
            $unmatchedSets = [];
            $unmatched = [];
            $UNMATCHED_CAP = 500;

            $this->info('📊 Processing records...');
            $bar = $this->output->createProgressBar();
            $bar->start();

            $lineIndex = 0;
            foreach ($file as $row) {
                // Ensure we have an array row (SplFileObject may return non-array for blank lines)
                if (! is_array($row)) {
                    $lineIndex++;

                    continue;
                }

                $allEmpty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && $cell !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) {
                    $lineIndex++;

                    continue;
                }

                // Map row to associative record if headers were provided
                if (is_array($headers)) {
                    // Normalize empty cells and encoding to UTF-8 (CSV may be in Windows-1256 / CP1256)
                    $row = array_map(function ($v) {
                        $v = $v === null ? '' : $v;

                        return $this->normalizeEncoding($v);
                    }, $row);
                    if (count($row) < count($headers)) {
                        $row = array_pad($row, count($headers), '');
                    } elseif (count($row) > count($headers)) {
                        $row = array_slice($row, 0, count($headers));
                    }

                    $record = @array_combine($headers, $row) ?: [];
                } else {
                    // Normalize encoding for positional rows as well
                    $record = array_map(function ($v) {
                        $v = $v === null ? '' : $v;

                        return $this->normalizeEncoding($v);
                    }, $row);
                }

                $offset = $lineIndex;
                $totalRows++;

                try {
                    // Prepare voter data
                    $voterData = VoterImportHelper::prepareVoterData($record);

                    // Validate required fields
                    if (empty($voterData['first_name']) || empty($voterData['family_name'])) {
                        $errors[] = [
                            'row' => $offset + 1,
                            'error' => 'Missing required fields (first_name or family_name)',
                            'data' => $record,
                        ];
                        $errorCount++;

                        continue;
                    }

                    // Track unmatched values (store as limited unique sets)
                    if ($showUnmatched) {
                        foreach (['gender', 'town', 'profession', 'country', 'sect', 'belong'] as $field) {
                            $val = isset($record[$field]) ? trim((string) $record[$field]) : '';
                            if ($val === '') {
                                continue;
                            }

                            $idKey = $field.'_id';
                            if (empty($voterData[$idKey])) {
                                if (! isset($unmatchedSets[$field])) {
                                    $unmatchedSets[$field] = [];
                                }

                                if (count($unmatchedSets[$field]) < $UNMATCHED_CAP) {
                                    $unmatchedSets[$field][$val] = true;
                                }
                            }
                        }
                    }

                    if (! $dryRun) {
                        $batch[] = array_merge($voterData, [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Insert batch when size reached
                        if (count($batch) >= $batchSize) {
                            DB::transaction(function () use (&$batch, &$successCount) {
                                DB::table('voters')->insert($batch);
                                $successCount += count($batch);
                                $batch = [];
                            });
                        }
                    } else {
                        $successCount++;
                    }

                } catch (\Exception $e) {
                    // Only keep a bounded list of errors in memory; count all errors.
                    if (count($errors) < 100) {
                        $errors[] = [
                            'row' => $offset + 1,
                            'error' => $e->getMessage(),
                            'data' => $record,
                        ];
                    }
                    $errorCount++;
                }

                $bar->advance();
            }

            // Insert remaining batch
            if (! $dryRun && count($batch) > 0) {
                DB::transaction(function () use (&$batch, &$successCount) {
                    DB::table('voters')->insert($batch);
                    $successCount += count($batch);
                    $batch = [];
                });
            }

            $bar->finish();
            $this->newLine(2);

            // Show results
            $this->info('✅ Import completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Rows', number_format($totalRows)],
                    ['Successfully Processed', number_format($successCount)],
                    ['Errors', number_format($errorCount)],
                    ['Success Rate', round(($successCount / $totalRows) * 100, 2).'%'],
                ]
            );

            // Show unmatched reference data
            if ($showUnmatched && ! empty($unmatchedSets)) {
                $this->warn("\n⚠️  Unmatched Reference Data:");
                foreach ($unmatchedSets as $field => $set) {
                    $uniqueValues = array_keys($set);
                    $this->warn("\n{$field} (".count($uniqueValues).' unique values, capped at '.$UNMATCHED_CAP.'):');
                    foreach (array_slice($uniqueValues, 0, 10) as $value) {
                        $this->line("  - {$value}");
                    }
                    if (count($uniqueValues) > 10) {
                        $this->line('  ... and '.(count($uniqueValues) - 10).' more');
                    }
                }
            }

            // Show first few errors
            if ($errorCount > 0) {
                $this->error("\n❌ Errors occurred during import:");
                foreach (array_slice($errors, 0, 5) as $error) {
                    $this->error("Row {$error['row']}: {$error['error']}");
                }
                if ($errorCount > 5) {
                    $this->error('... and '.($errorCount - 5).' more errors');
                }

                // Offer to save error log
                if ($this->confirm('Save detailed error log?', true)) {
                    $errorFile = storage_path('logs/voter_import_errors_'.date('Y-m-d_His').'.json');
                    file_put_contents($errorFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->info("Error log saved to: {$errorFile}");
                }
            }

            Log::info('Voter import completed', [
                'file' => $filePath,
                'total' => $totalRows,
                'success' => $successCount,
                'errors' => $errorCount,
                'dry_run' => $dryRun,
            ]);

            // Clear caches
            VoterImportHelper::clearCaches();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());
            Log::error('Voter import failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Normalize encoding for an input string, attempting common encodings
     * used for Arabic CSV exports and returning a UTF-8 string.
     */
    protected function normalizeEncoding(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // If already valid UTF-8, return as-is
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Try common single-byte encodings used for Arabic: Windows-1256 / CP1256, ISO-8859-6
        $encodings = ['WINDOWS-1256', 'ISO-8859-6', 'ISO-8859-1'];
        // Only try encodings supported by mbstring on this system
        $available = array_map('strtoupper', mb_list_encodings());

        foreach ($encodings as $enc) {
            if (! in_array(strtoupper($enc), $available, true)) {
                continue;
            }

            $converted = @mb_convert_encoding($value, 'UTF-8', $enc);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        // As a last resort, attempt iconv with transliteration
        $converted = @iconv('CP1256', 'UTF-8//TRANSLIT', $value);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        // If we still can't convert, return the original string (will likely cause DB error later)
        return $value;
    }
}
