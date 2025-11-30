<?php

namespace App\Console\Commands;

use App\Models\Voter;
use App\Helpers\VoterImportHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class ImportVoters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voters:import 
                            {file : Path to CSV file}
                            {--batch=1000 : Number of records to process per batch}
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
    public function handle()
    {
        $filePath = $this->argument('file');
        $batchSize = (int) $this->option('batch');
        $skipHeader = $this->option('skip-header');
        $dryRun = $this->option('dry-run');
        $showUnmatched = $this->option('show-unmatched');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("🚀 Starting voter import from: {$filePath}");
        $this->info("Batch size: {$batchSize}");
        
        if ($dryRun) {
            $this->warn("⚠️  DRY RUN MODE - No data will be saved");
        }

        try {
            // Initialize caches for performance
            $this->info("📦 Loading reference data into cache...");
            VoterImportHelper::initializeCaches();
            
            // Read CSV file
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter(',');
            
            if ($skipHeader) {
                $csv->setHeaderOffset(0);
                $records = $csv->getRecords();
            } else {
                $records = $csv->getRecords();
            }

            $totalRows = 0;
            $successCount = 0;
            $errorCount = 0;
            $batch = [];
            $errors = [];
            $unmatched = [];

            $this->info("📊 Processing records...");
            $bar = $this->output->createProgressBar();
            $bar->start();

            foreach ($records as $offset => $record) {
                $totalRows++;

                try {
                    // Prepare voter data
                    $voterData = VoterImportHelper::prepareVoterData($record);

                    // Validate required fields
                    if (empty($voterData['first_name']) || empty($voterData['family_name'])) {
                        $errors[] = [
                            'row' => $offset + 1,
                            'error' => 'Missing required fields (first_name or family_name)',
                            'data' => $record
                        ];
                        $errorCount++;
                        continue;
                    }

                    // Track unmatched values
                    if ($showUnmatched) {
                        foreach (['gender', 'town', 'profession', 'country', 'doctrine', 'belong'] as $field) {
                            if (!empty($record[$field]) && empty($voterData["{$field}_id"])) {
                                $unmatched[$field][] = $record[$field];
                            }
                        }
                    }

                    if (!$dryRun) {
                        $batch[] = array_merge($voterData, [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Insert batch when size reached
                        if (count($batch) >= $batchSize) {
                            DB::table('voters')->insert($batch);
                            $successCount += count($batch);
                            $batch = [];
                        }
                    } else {
                        $successCount++;
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $offset + 1,
                        'error' => $e->getMessage(),
                        'data' => $record
                    ];
                    $errorCount++;
                }

                $bar->advance();
            }

            // Insert remaining batch
            if (!$dryRun && count($batch) > 0) {
                DB::table('voters')->insert($batch);
                $successCount += count($batch);
            }

            $bar->finish();
            $this->newLine(2);

            // Show results
            $this->info("✅ Import completed!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Rows', number_format($totalRows)],
                    ['Successfully Processed', number_format($successCount)],
                    ['Errors', number_format($errorCount)],
                    ['Success Rate', round(($successCount / $totalRows) * 100, 2) . '%'],
                ]
            );

            // Show unmatched reference data
            if ($showUnmatched && !empty($unmatched)) {
                $this->warn("\n⚠️  Unmatched Reference Data:");
                foreach ($unmatched as $field => $values) {
                    $uniqueValues = array_unique($values);
                    if (count($uniqueValues) > 0) {
                        $this->warn("\n{$field} (" . count($uniqueValues) . " unique values):");
                        foreach (array_slice($uniqueValues, 0, 10) as $value) {
                            $this->line("  - {$value}");
                        }
                        if (count($uniqueValues) > 10) {
                            $this->line("  ... and " . (count($uniqueValues) - 10) . " more");
                        }
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
                    $this->error("... and " . ($errorCount - 5) . " more errors");
                }
                
                // Offer to save error log
                if ($this->confirm('Save detailed error log?', true)) {
                    $errorFile = storage_path('logs/voter_import_errors_' . date('Y-m-d_His') . '.json');
                    file_put_contents($errorFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->info("Error log saved to: {$errorFile}");
                }
            }

            Log::info('Voter import completed', [
                'file' => $filePath,
                'total' => $totalRows,
                'success' => $successCount,
                'errors' => $errorCount,
                'dry_run' => $dryRun
            ]);

            // Clear caches
            VoterImportHelper::clearCaches();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Import failed: " . $e->getMessage());
            Log::error('Voter import failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
