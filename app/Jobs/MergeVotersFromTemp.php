<?php

namespace App\Jobs;

use App\Events\VoterUploadCleaned;
use App\Events\VoterUploadImported;
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

class MergeVotersFromTemp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        // Check if there's any data to merge
        $tempCount = VoterImportTemp::count();

        if ($tempCount === 0) {
            Log::info('MergeVotersFromTemp: No temp rows found, skipping merge', ['upload_id' => $this->upload->id]);

            $this->upload->status = 'completed';
            $this->upload->meta = array_merge($meta, [
                'merge_skipped' => true,
                'merge_reason' => 'no_unprocessed_rows',
            ]);
            $this->upload->save();

            return;
        }

        $this->upload->status = 'merging';
        $this->upload->save();

        $dryRun = (bool) ($this->options['dry_run'] ?? false);
        $startTime = microtime(true);

        Log::info('MergeVotersFromTemp: Starting optimized merge', [
            'upload_id' => $this->upload->id,
            'temp_rows' => $tempCount,
            'dry_run' => $dryRun,
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        if (! $dryRun) {
            DB::beginTransaction();
            try {
                // Use INSERT ... ON DUPLICATE KEY UPDATE for maximum performance
                // This single query handles both inserts and updates
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

                // Get counts
                $inserted = DB::affectedRows();

                DB::commit();

                Log::info('MergeVotersFromTemp: Bulk merge completed', [
                    'upload_id' => $this->upload->id,
                    'affected_rows' => $inserted,
                    'elapsed_seconds' => round(microtime(true) - $startTime, 2),
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('MergeVotersFromTemp: Bulk merge failed', [
                    'upload_id' => $this->upload->id,
                    'exception' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $endTime = microtime(true);
        $report = [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'completed_at' => now()->toDateTimeString(),
        ];

        $this->upload->meta = array_merge($meta, ['import_report' => $report]);
        $this->upload->status = 'imported';
        $this->upload->save();

        // Broadcast event that import finished so admin UI updates in real-time
        try {
            event(new VoterUploadCleaned($this->upload));
            event(new VoterUploadImported($this->upload));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast VoterUploadCleaned after merge', ['upload_id' => $this->upload->id, 'exception' => $e->getMessage()]);
        }

        Log::info('MergeVotersFromTemp completed', ['upload_id' => $this->upload->id, 'report' => $report]);

        // Chain soft delete and assign families jobs (if not dry run)
        if (! $dryRun) {
            try {
                // Get or create ImportBatch for this upload
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

                // Dispatch soft delete job (applies deletes, not dry run)
                Log::info('MergeVotersFromTemp: dispatching SoftDeleteMissingVoters', ['batch_id' => $batch->id]);
                SoftDeleteMissingVoters::dispatch($batch, ['dry_run' => false])->onQueue('imports');
            } catch (\Throwable $e) {
                Log::error('Failed to chain post-merge jobs', ['upload_id' => $this->upload->id, 'exception' => $e->getMessage()]);
            }
        }
    }
}
