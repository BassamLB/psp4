<?php

namespace App\Jobs;

use App\Events\VoterUploadCleaned;
use App\Events\VoterUploadImported;
use App\Models\VoterImportTemp;
use App\Models\VoterUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeVotersFromTempOptimized implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    // Set a sensible chunk size for deletes to prevent excessive locks/memory
    private const DELETE_CHUNK_SIZE = 10000;

    public function __construct(public VoterUpload $upload, public array $options = [])
    {
        $this->onQueue('imports');
        $this->onConnection('redis');
    }

    public function uniqueId(): string
    {
        return 'merge-voters-'.$this->upload->id;
    }

    public function handle(): void
    {
        Log::info('MergeVotersFromTempOptimized: Job started', ['upload_id' => $this->upload->id]);
        try {
            /** @var array $meta */
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

            // Detect district_id from the imported data by joining temp with towns
            $districtIds = DB::table('voter_imports_temp')
                ->join('towns', 'voter_imports_temp.town_id', '=', 'towns.id')
                ->whereNotNull('towns.district_id')
                ->distinct()
                ->pluck('towns.district_id');

            if ($districtIds->isEmpty()) {
                Log::error('MergeVotersFromTempOptimized: No district_id found in temp data', ['upload_id' => $this->upload->id]);
                $this->upload->status = 'error';
                $this->upload->meta = array_merge($meta, ['merge_error' => 'no_district_found']);
                $this->upload->save();

                return;
            }

            if ($districtIds->count() > 1) {
                Log::warning('MergeVotersFromTempOptimized: Multiple districts in import', [
                    'upload_id' => $this->upload->id,
                    'districts' => $districtIds->toArray(),
                ]);
            }

            // Use the first district (or you could require single-district imports)
            $districtId = $districtIds->first();

            Log::info('MergeVotersFromTempOptimized: Starting', [
                'upload_id' => $this->upload->id,
                'temp_rows' => $tempCount,
                'district_id' => $districtId,
            ]);

            $insertedCount = 0;
            $updatedCount = 0;
            $softDeletedCount = 0;

            if (! $dryRun) {
                try {
                    $districtTownIds = DB::table('towns')
                        ->where('district_id', $districtId)
                        ->pluck('id');

                    if ($districtTownIds->isEmpty()) {
                        Log::warning('MergeVotersFromTempOptimized: No towns found for district', [
                            'upload_id' => $this->upload->id,
                            'district_id' => $districtId,
                        ]);

                        return;
                    }

                    $idsList = implode(',', $districtTownIds->toArray());
                    $now = now();

                    $updatedCount = 0; // Insert-only mode

                    Log::info('MergeVotersFromTempOptimized: Skipping updates - insert-only mode', [
                        'upload_id' => $this->upload->id,
                        'district_towns' => $districtTownIds->count(),
                    ]);

                    // --- 1. BULK INSERT ---
                    DB::beginTransaction();

                    Log::info('MergeVotersFromTempOptimized: Starting bulk insert', [
                        'upload_id' => $this->upload->id,
                    ]);

                    $insertSql = "INSERT INTO voters (sijil_number, town_id, first_name, family_name, father_name, mother_full_name, sijil_additional_string, gender_id, sect_id, personal_sect, date_of_birth, created_at, updated_at)
                    SELECT t.sijil_number, t.town_id, t.first_name, t.family_name, t.father_name, t.mother_full_name, t.sijil_additional_string, t.gender_id, t.sect_id, t.personal_sect, t.date_of_birth, ?, ?
                    FROM voter_imports_temp t
                    LEFT JOIN voters v ON v.merge_hash = t.merge_hash
                    WHERE v.id IS NULL
                    AND t.town_id IN ($idsList)";

                    $insertedCount = DB::affectingStatement($insertSql, [$now, $now]);

                    DB::commit(); // Commit the insert to free up transaction resources early

                    Log::info('MergeVotersFromTempOptimized: Bulk insert completed', [
                        'upload_id' => $this->upload->id,
                        'inserted_count' => $insertedCount,
                    ]);

                    // --- 2. CHUNKED SOFT DELETE ---
                    Log::info('MergeVotersFromTempOptimized: Starting CHUNKED soft delete', [
                        'upload_id' => $this->upload->id,
                        'chunk_size' => self::DELETE_CHUNK_SIZE,
                    ]);

                    $deletedInChunk = 0;
                    $offset = 0;
                    $limit = self::DELETE_CHUNK_SIZE;

                    do {
                        // SQL to find IDs for soft deletion in the current chunk
                        $selectIdsSql = "
                        SELECT v.id FROM voters v
                        WHERE v.town_id IN ({$idsList})
                        AND v.deleted_at IS NULL
                        AND NOT EXISTS (
                            SELECT 1 FROM voter_imports_temp t
                            WHERE t.merge_hash = v.merge_hash
                        )
                        LIMIT {$limit} OFFSET {$offset}";

                        // Use plain DB::select to fetch IDs
                        $voterIds = DB::select($selectIdsSql);
                        $idsToDelete = array_column($voterIds, 'id');

                        if (empty($idsToDelete)) {
                            break; // No more rows to delete
                        }

                        // Perform the chunked update within a transaction to maintain integrity
                        DB::beginTransaction();
                        $deletedCount = DB::table('voters')
                            ->whereIn('id', $idsToDelete)
                            ->update(['deleted_at' => $now]);
                        DB::commit();

                        $deletedInChunk = count($idsToDelete);
                        $softDeletedCount += $deletedCount;
                        $offset += $limit;

                        Log::info('MergeVotersFromTempOptimized: Soft delete progress', [
                            'upload_id' => $this->upload->id,
                            'total_deleted' => $softDeletedCount,
                            'chunk_offset' => $offset,
                        ]);
                    } while ($deletedInChunk === $limit); // Continue if the chunk was full

                    Log::info('MergeVotersFromTempOptimized: Soft delete completed', [
                        'upload_id' => $this->upload->id,
                        'district_id' => $districtId,
                        'soft_deleted_count' => $softDeletedCount,
                    ]);

                    $elapsedSeconds = round(microtime(true) - $startTime, 2);

                    Log::info('MergeVotersFromTempOptimized: Completed', [
                        'upload_id' => $this->upload->id,
                        'district_id' => $districtId,
                        'inserted' => $insertedCount,
                        'updated' => $updatedCount,
                        'soft_deleted' => $softDeletedCount,
                        'elapsed_seconds' => $elapsedSeconds,
                    ]);
                } catch (\Throwable $e) {
                    // Catch any remaining open transaction and roll it back
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }

                    Log::error('MergeVotersFromTempOptimized: Failed', [
                        'upload_id' => $this->upload->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            $report = [
                'district_id' => $districtId ?? null,
                'inserted_count' => $insertedCount,
                'updated_count' => $updatedCount,
                'soft_deleted_count' => $softDeletedCount,
                'dry_run' => $dryRun,
                'merge_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at' => now()->toDateTimeString(),
            ];

            $this->upload->meta = array_merge($meta, ['import_report' => $report]);
            $this->upload->status = 'imported';
            $this->upload->save();

            try {
                event(new VoterUploadCleaned($this->upload));
                event(new VoterUploadImported($this->upload));
            } catch (\Throwable $e) {
                // ignore
            }

            Log::info('MergeVotersFromTempOptimized: Report', ['upload_id' => $this->upload->id, 'report' => $report]);

            Log::info('MergeVotersFromTempOptimized: Workflow completed', [
                'upload_id' => $this->upload->id,
            ]);
        } catch (\Throwable $e) {
            // This catch block is critical for catching initial setup failures
            Log::error('MergeVotersFromTempOptimized: Initial setup failed', [
                'upload_id' => $this->upload->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to allow job retries
        }
    }
}
