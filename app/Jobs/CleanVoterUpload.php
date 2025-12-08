<?php

namespace App\Jobs;

use App\Events\VoterUploadCleaned;
use App\Models\VoterUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CleanVoterUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes

    public $tries = 2;

    private const STORAGE_DIR = 'private/imports';

    private const SCRIPT_TIMEOUT = 540; // 9 minutes (less than job timeout)

    public function __construct(public VoterUpload $upload)
    {
        $this->onQueue('cleaning');
    }

    public function handle(): void
    {
        Log::info('CleanVoterUpload: Starting', [
            'upload_id' => $this->upload->id,
            'filename' => $this->upload->filename,
        ]);

        try {
            $inputPath = $this->resolveInputPath();

            if (! $inputPath) {
                $this->handleInputNotFound();

                return;
            }

            $this->updateStatus('cleaning');

            $outputPaths = $this->prepareOutputPaths();
            $result = $this->executeCleaningScript($inputPath, $outputPaths['full']);

            $this->handleCleaningResult($result, $outputPaths);

        } catch (\Throwable $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Resolve input file path with simplified logic
     */
    protected function resolveInputPath(): ?string
    {
        $filename = basename($this->upload->path);
        $standardPath = storage_path('app/'.self::STORAGE_DIR.'/'.$filename);

        // Primary check: standard location
        if (file_exists($standardPath)) {
            Log::info('CleanVoterUpload: Input file found', [
                'path' => $standardPath,
                'upload_id' => $this->upload->id,
            ]);

            return $standardPath;
        }

        // Fallback: check if the stored path is already absolute
        $storedPath = $this->upload->path;
        if ($this->isAbsolutePath($storedPath) && file_exists($storedPath)) {
            Log::info('CleanVoterUpload: Input file found at stored path', [
                'path' => $storedPath,
                'upload_id' => $this->upload->id,
            ]);

            return $storedPath;
        }

        // No file found
        Log::warning('CleanVoterUpload: Input file not found', [
            'expected' => $standardPath,
            'stored_path' => $storedPath,
            'upload_id' => $this->upload->id,
        ]);

        return null;
    }

    /**
     * Check if path is absolute
     */
    protected function isAbsolutePath(string $path): bool
    {
        return strpos($path, DIRECTORY_SEPARATOR) === 0 ||
               preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    /**
     * Handle case when input file is not found
     */
    protected function handleInputNotFound(): void
    {
        $this->upload->status = 'clean_failed';
        $this->upload->meta = array_merge($this->upload->meta ?? [], [
            'error' => 'input_not_found',
            'searched_path' => storage_path('app/'.self::STORAGE_DIR.'/'.basename($this->upload->path)),
            'timestamp' => now()->toIso8601String(),
        ]);
        $this->upload->save();

        $this->broadcastEvent();
    }

    /**
     * Prepare output file paths
     */
    protected function prepareOutputPaths(): array
    {
        $baseFilename = pathinfo($this->upload->filename, PATHINFO_FILENAME);
        $extension = pathinfo($this->upload->filename, PATHINFO_EXTENSION);

        $cleanedFilename = "cleaned_{$baseFilename}.{$extension}";
        $invalidFilename = "{$baseFilename}.invalid_rows.csv";

        $cleanedRel = self::STORAGE_DIR.'/'.$cleanedFilename;
        $invalidRel = self::STORAGE_DIR.'/'.$invalidFilename;

        $cleanedFull = storage_path('app/'.$cleanedRel);
        $invalidFull = storage_path('app/'.$invalidRel);

        // Ensure directory exists
        $dir = dirname($cleanedFull);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return [
            'full' => $cleanedFull,
            'invalid_full' => $invalidFull,
            'cleaned_meta' => 'app/'.$cleanedRel,
            'invalid_meta' => 'app/'.$invalidRel,
        ];
    }

    /**
     * Execute cleaning script with timeout protection
     */
    protected function executeCleaningScript(string $inputPath, string $outputPath): array
    {
        $scriptPath = base_path('scripts/clean_and_normalize_csv.php');

        if (! file_exists($scriptPath)) {
            throw new \Exception("Cleaning script not found: {$scriptPath}");
        }

        Log::info('CleanVoterUpload: Executing cleaning script', [
            'input' => $inputPath,
            'output' => $outputPath,
            'upload_id' => $this->upload->id,
        ]);

        $startTime = microtime(true);

        try {
            // Use Laravel's Process facade for better control
            $result = Process::timeout(self::SCRIPT_TIMEOUT)
                ->run([PHP_BINARY, $scriptPath, $inputPath, $outputPath]);

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info('CleanVoterUpload: Script completed', [
                'exit_code' => $result->exitCode(),
                'elapsed_seconds' => $elapsed,
                'upload_id' => $this->upload->id,
            ]);

            return [
                'success' => $result->successful(),
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'elapsed' => $elapsed,
            ];

        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            throw new \Exception('Cleaning script timed out after '.self::SCRIPT_TIMEOUT.' seconds');
        }
    }

    /**
     * Handle cleaning result and update upload
     */
    protected function handleCleaningResult(array $result, array $paths): void
    {
        $meta = array_merge($this->upload->meta ?? [], [
            'cleaned_path' => $paths['cleaned_meta'],
            'invalid_path' => $paths['invalid_meta'],
            'clean_output' => $result['output'],
            'clean_exit' => $result['exit_code'],
            'clean_elapsed' => $result['elapsed'],
            'cleaned_at' => now()->toIso8601String(),
        ]);

        // Add file size info for monitoring
        if (file_exists($paths['full'])) {
            $meta['cleaned_file_size'] = filesize($paths['full']);
        }

        if (file_exists($paths['invalid_full'])) {
            $meta['invalid_file_size'] = filesize($paths['invalid_full']);
            // Count invalid rows (excluding header)
            $meta['invalid_rows_count'] = max(0, count(file($paths['invalid_full'])) - 1);
        } else {
            $meta['invalid_rows_count'] = 0;
        }

        // Remove error flag on success
        if ($result['success']) {
            unset($meta['error']);
        } else {
            $meta['error'] = 'cleaning_script_failed';
            $meta['error_output'] = $result['error'];
        }

        $this->upload->meta = $meta;
        $this->upload->status = $result['success'] ? 'cleaned' : 'clean_failed';
        $this->upload->save();

        Log::info('CleanVoterUpload: Completed', [
            'upload_id' => $this->upload->id,
            'status' => $this->upload->status,
            'exit_code' => $result['exit_code'],
            'invalid_rows' => $meta['invalid_rows_count'],
        ]);

        $this->broadcastEvent();

        // Chain next job if successful
        if ($result['success']) {
            $this->chainNextJob();
        }
    }

    /**
     * Handle job failure
     */
    protected function handleFailure(\Throwable $e): void
    {
        Log::error('CleanVoterUpload: Failed', [
            'upload_id' => $this->upload->id,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        try {
            $this->upload->status = 'clean_failed';
            $this->upload->meta = array_merge($this->upload->meta ?? [], [
                'error' => 'exception',
                'exception_message' => $e->getMessage(),
                'exception_type' => get_class($e),
                'failed_at' => now()->toIso8601String(),
            ]);
            $this->upload->save();

            $this->broadcastEvent();
        } catch (\Throwable $savingError) {
            Log::error('CleanVoterUpload: Failed to save error state', [
                'upload_id' => $this->upload->id,
                'error' => $savingError->getMessage(),
            ]);
        }

        throw $e; // Re-throw for Laravel's failed job handling
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
     * Broadcast event to notify frontend
     */
    protected function broadcastEvent(): void
    {
        try {
            event(new VoterUploadCleaned($this->upload));
        } catch (\Throwable $e) {
            Log::warning('CleanVoterUpload: Failed to broadcast event', [
                'upload_id' => $this->upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Chain next job in the pipeline
     */
    protected function chainNextJob(): void
    {
        try {
            ImportVotersToTempTable::dispatch($this->upload, [])
                ->onQueue('imports');

            Log::info('CleanVoterUpload: Chained ImportVotersToTempTable', [
                'upload_id' => $this->upload->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CleanVoterUpload: Failed to chain next job', [
                'upload_id' => $this->upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle failed job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanVoterUpload: Job failed permanently', [
            'upload_id' => $this->upload->id,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $this->upload->status = 'clean_failed';
            $this->upload->meta = array_merge($this->upload->meta ?? [], [
                'error' => 'job_failed_permanently',
                'exception' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ]);
            $this->upload->save();

            $this->broadcastEvent();
        } catch (\Throwable $e) {
            Log::error('CleanVoterUpload: Failed to handle job failure', [
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
            'cleaning',
            'upload:'.$this->upload->id,
        ];
    }
}
