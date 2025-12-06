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

class CleanVoterUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public VoterUpload $upload) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Input path resolution: try several likely locations so the job is resilient
            $rel = $this->upload->path;

            $candidates = [];

            // If the stored path already looks absolute, try it first
            if (strpos($rel, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:\\\\/', $rel)) {
                $candidates[] = $rel;
            }

            // Common storage locations
            $candidates[] = storage_path('app/private/imports/'.$rel);
            $candidates[] = storage_path('app/private/'.$rel);
            $candidates[] = storage_path('app/'.$rel);

            // Sometimes the stored path accidentally contains a leading 'imports/'
            // or duplicates it (eg. 'imports/xyz' or 'imports/imports/xyz'). Try
            // stripping a leading 'imports/' to recover the real path.
            if (strpos($rel, 'imports/') === 0) {
                $relStripped = preg_replace('#^imports/+#', '', $rel);
                $candidates[] = storage_path('app/private/imports/'.$relStripped);
                $candidates[] = storage_path('app/private/'.$relStripped);
                $candidates[] = storage_path('app/'.$relStripped);
            }

            // Also try basename fallback
            $candidates[] = storage_path('app/private/imports/'.basename($rel));

            $input = null;
            $tried = [];
            foreach ($candidates as $cand) {
                $tried[] = $cand;
                if (file_exists($cand)) {
                    $input = $cand;
                    break;
                }
            }

            if (! $input) {
                Log::warning('CleanVoterUpload: input file not found (tried multiple locations)', ['tried' => $tried, 'upload_id' => $this->upload->id]);
                $this->upload->status = 'clean_failed';
                $this->upload->meta = array_merge($this->upload->meta ?? [], ['error' => 'input_not_found', 'tried' => $tried]);
                $this->upload->save();

                return;
            }

            // Storage location (relative to storage/app) and metadata location (prefixed with 'app/')
            $storageBase = 'private/imports';
            $storageCleanRel = $storageBase.'/cleaned_'.$this->upload->filename;
            $outputFull = storage_path('app/'.$storageCleanRel);

            $storageInvalidRel = $storageBase.'/'.pathinfo($this->upload->filename, PATHINFO_FILENAME).'.invalid_rows.csv';

            // Ensure target directory exists so the script can write files
            $outDir = dirname($outputFull);
            if (! is_dir($outDir)) {
                mkdir($outDir, 0755, true);
            }

            // Metadata paths include the 'app/' prefix so they consistently reference storage/app/...
            $metaCleanedPath = 'app/'.$storageCleanRel;
            $metaInvalidPath = 'app/'.$storageInvalidRel;

            $php = PHP_BINARY;
            $script = base_path('scripts/clean_and_normalize_csv.php');

            $cmd = escapeshellarg($php).' '.escapeshellarg($script).' '.escapeshellarg($input).' '.escapeshellarg($outputFull);

            // Log the command for debugging if something goes wrong
            Log::info('CleanVoterUpload: running command', ['cmd' => $cmd, 'upload_id' => $this->upload->id]);

            exec($cmd.' 2>&1', $lines, $exitCode);
            $outputText = implode("\n", $lines);

            $meta = array_merge($this->upload->meta ?? [], [
                'cleaned_path' => $metaCleanedPath,
                'invalid_path' => $metaInvalidPath,
                'clean_output' => $outputText,
                'clean_exit' => $exitCode,
                'resolved_path' => $input,
            ]);

            // If command succeeded remove any previous transient error entries
            if ($exitCode === 0) {
                if (isset($meta['error'])) {
                    unset($meta['error']);
                }
            }

            $this->upload->meta = $meta;
            $this->upload->status = $exitCode === 0 ? 'cleaned' : 'clean_failed';
            $this->upload->save();

            Log::info('CleanVoterUpload finished', ['upload_id' => $this->upload->id, 'exit' => $exitCode]);

            // Broadcast to admin channel so UI can show a notification
            try {
                event(new VoterUploadCleaned($this->upload));
            } catch (\Throwable $e) {
                Log::warning('Failed to broadcast VoterUploadCleaned', ['upload_id' => $this->upload->id, 'exception' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('CleanVoterUpload failed', ['exception' => $e, 'upload_id' => $this->upload->id]);
            try {
                $this->upload->status = 'clean_failed';
                $this->upload->meta = array_merge($this->upload->meta ?? [], ['exception' => $e->getMessage()]);
                $this->upload->save();

                // Notify admins that cleaning failed
                try {
                    event(new VoterUploadCleaned($this->upload));
                } catch (\Throwable $_e) {
                    Log::warning('Failed to broadcast VoterUploadCleaned (failure path)', ['upload_id' => $this->upload->id, 'exception' => $_e->getMessage()]);
                }
            } catch (\Throwable $_) {
            } catch (\Throwable) {
                // swallow
            }
        }
    }
}
