<?php

namespace App\Jobs;

use App\Events\VoterUploadCleaned;
use App\Events\VoterUploadProgress;
use App\Models\ImportBatch;
use App\Models\VoterImportTemp;
use App\Models\VoterUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeVotersFromTempOptimized implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public VoterUpload $upload, public array $options = [])
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $meta = $this->upload->meta ?? [];
        $tempCount = VoterImportTemp::count();

        if ($tempCount === 0) {
            Log::info('MergeVotersFromTempOptimized: No temp rows found', ['upload_id' => $this->upload->id]);
            $this->upload->status = 'completed';
            $this->upload->meta = array_merge($meta, ['merge_skipped' => true]);
            $this->upload->save();

            return;
        }

        $this->upload->status = 'merging';
        $this->upload->save();

        $dryRun = (bool) ($this->options['dry_run'] ?? false);
        $startTime = microtime(true);

        Log::info('MergeVotersFromTempOptimized: Starting', [
            'upload_id' => $this->upload->id,
            'temp_rows' => $tempCount,
        ]);

        try {
            event(new VoterUploadProgress($this->upload, [
                'percent' => 10,
                'phase' => 'merging',
                'message' => 'Starting optimized merge',
            ]));
        } catch (\Throwable $e) {
            // ignore
        }

        $affectedRows = 0;

        if (! $dryRun) {
            try {
                // Single query: INSERT with ON DUPLICATE KEY UPDATE
                // This is orders of magnitude faster than chunk-based processing
                $sql = '
                    INSERT INTO voters (
                        sijil_number, town_id, first_name, family_name, father_name, mother_full_name,
                        sijil_additional_string, gender_id, sect_id, personal_sect, date_of_birth,
                        created_at, updated_at
                    )
                    SELECT 
                        sijil_number, town_id, first_name, family_name, father_name, mother_full_name,
                        sijil_additional_string, gender_id, sect_id, personal_sect, date_of_birth,
                        NOW(), NOW()
                    FROM voter_imports_temp
                    WHERE sijil_number IS NOT NULL AND town_id IS NOT NULL
                    ON DUPLICATE KEY UPDATE
                        sijil_additional_string = VALUES(sijil_additional_string),
                        gender_id = VALUES(gender_id),
                        sect_id = VALUES(sect_id),
                        personal_sect = VALUES(personal_sect),
                        date_of_birth = VALUES(date_of_birth),
                        updated_at = NOW()
                ';

                DB::statement($sql);
                $affectedRows = DB::getPdo()->lastInsertId() ?: $tempCount;

                $elapsedSeconds = round(microtime(true) - $startTime, 2);

                Log::info('MergeVotersFromTempOptimized: Completed', [
                    'upload_id' => $this->upload->id,
                    'affected_rows' => $affectedRows,
                    'elapsed_seconds' => $elapsedSeconds,
                ]);
            } catch (\Throwable $e) {
                Log::error('MergeVotersFromTempOptimized: Failed', [
                    'upload_id' => $this->upload->id,
                    'exception' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        try {
            event(new VoterUploadProgress($this->upload, [
                'percent' => 90,
                'phase' => 'merge_completed',
                'message' => 'Merge completed',
            ]));
        } catch (\Throwable $e) {
            // ignore
        }

        $report = [
            'affected_rows' => $affectedRows,
            'dry_run' => $dryRun,
            'merge_seconds' => round(microtime(true) - $startTime, 2),
            'completed_at' => now()->toDateTimeString(),
        ];

        $this->upload->meta = array_merge($meta, ['import_report' => $report]);
        $this->upload->status = 'imported';
        $this->upload->save();

        try {
            event(new VoterUploadCleaned($this->upload));
        } catch (\Throwable $e) {
            // ignore
        }

        Log::info('MergeVotersFromTempOptimized: Report', ['upload_id' => $this->upload->id, 'report' => $report]);

        // Chain post-merge jobs
        if (! $dryRun) {
            try {
                $batchId = $this->upload->meta['import_batch_id'] ?? null;
                $batch = $batchId ? ImportBatch::find($batchId) : null;

                if (! $batch) {
                    $batch = ImportBatch::create([
                        'voter_upload_id' => $this->upload->id,
                        'user_id' => null,
                        'district_id' => null,
                        'options' => $this->options,
                        'status' => 'merged',
                    ]);
                    $this->upload->meta = array_merge($meta, ['import_batch_id' => $batch->id]);
                    $this->upload->save();
                }

                Log::info('MergeVotersFromTempOptimized: dispatching SoftDeleteMissingVoters');
                SoftDeleteMissingVoters::dispatch($batch, ['dry_run' => false])->onQueue('imports');

                Log::info('MergeVotersFromTempOptimized: dispatching AssignFamiliesToVoters');
                AssignFamiliesToVoters::dispatch([])->onQueue('imports');
            } catch (\Throwable $e) {
                Log::error('Failed to chain post-merge jobs', ['exception' => $e->getMessage()]);
            }
        }
    }
}
