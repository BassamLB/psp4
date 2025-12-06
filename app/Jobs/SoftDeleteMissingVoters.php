<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoftDeleteMissingVoters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(public ImportBatch $batch, public array $options = [])
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $batch = $this->batch;
        $dryRun = (bool) ($this->options['dry_run'] ?? true);

        $batchId = $batch->id;

        Log::info('SoftDeleteMissingVoters: starting', ['batch_id' => $batchId, 'dry_run' => $dryRun]);

        // Create a temporary table with full 6-field identity key for efficient comparison
        // Using shorter lengths to avoid MySQL key length limit (3072 bytes)
        DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS temp_imported_voters (
            sijil_number INT,
            town_id INT,
            first_name VARCHAR(100) COLLATE utf8mb4_unicode_ci,
            family_name VARCHAR(100) COLLATE utf8mb4_unicode_ci,
            father_name VARCHAR(100) COLLATE utf8mb4_unicode_ci,
            mother_full_name VARCHAR(100) COLLATE utf8mb4_unicode_ci,
            KEY idx_identity (sijil_number, town_id, first_name, family_name, father_name, mother_full_name)
        )');
        DB::statement('TRUNCATE TABLE temp_imported_voters');

        // Bulk insert imported identity keys in chunks
        $chunkSize = 1000;
        $offset = 0;
        $insertedCount = 0;

        while (true) {
            $rows = DB::table('voter_imports_temp')
                ->select('sijil_number', 'town_id', 'first_name', 'family_name', 'father_name', 'mother_full_name')
                ->offset($offset)
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $values = [];
            foreach ($rows as $r) {
                $sijil = (int) ($r->sijil_number ?? 0);
                $town = (int) $r->town_id;
                $first = DB::connection()->getPdo()->quote($r->first_name ?? '');
                $family = DB::connection()->getPdo()->quote($r->family_name ?? '');
                $father = DB::connection()->getPdo()->quote($r->father_name ?? '');
                $mother = DB::connection()->getPdo()->quote($r->mother_full_name ?? '');
                $values[] = "({$sijil}, {$town}, {$first}, {$family}, {$father}, {$mother})";
            }

            if (! empty($values)) {
                $valueStr = implode(',', $values);
                DB::statement("INSERT INTO temp_imported_voters (sijil_number, town_id, first_name, family_name, father_name, mother_full_name) VALUES {$valueStr}");
                $insertedCount += $rows->count();
            }

            $offset += $chunkSize;
            // Only log every 50,000 rows to reduce I/O
            if ($insertedCount % 50000 === 0) {
                Log::info('SoftDeleteMissingVoters: loaded temp data', ['inserted' => $insertedCount, 'batch_id' => $batchId]);
            }
        }

        // Find voters to delete using a single efficient query with full 6-field identity match
        $toDeleteQuery = DB::table('voters')
            ->select('voters.id')
            ->leftJoin('temp_imported_voters', function ($join) {
                $join->on('voters.sijil_number', '=', 'temp_imported_voters.sijil_number')
                    ->on('voters.town_id', '=', 'temp_imported_voters.town_id')
                    ->on('voters.first_name', '=', 'temp_imported_voters.first_name')
                    ->on('voters.family_name', '=', 'temp_imported_voters.family_name')
                    ->on('voters.father_name', '=', 'temp_imported_voters.father_name')
                    ->on('voters.mother_full_name', '=', 'temp_imported_voters.mother_full_name');
            })
            ->whereNull('temp_imported_voters.sijil_number')
            ->whereNull('voters.deleted_at');

        $toDelete = $toDeleteQuery->pluck('id')->all();

        Log::info('SoftDeleteMissingVoters: candidates found', ['count' => count($toDelete), 'batch_id' => $batchId]);

        $report = [
            'batch_id' => $batchId,
            'dry_run' => $dryRun,
            'candidates' => count($toDelete),
            'sample_ids' => array_slice($toDelete, 0, 50),
            'generated_at' => now()->toDateTimeString(),
        ];

        if (! $dryRun && ! empty($toDelete)) {
            // Soft delete voters in chunks
            $chunks = array_chunk($toDelete, 1000);
            $deleted = 0;
            foreach ($chunks as $chunk) {
                $count = Voter::whereIn('id', $chunk)->delete();
                $deleted += $count;
                // Only log every 10 chunks (10,000 rows) to reduce I/O
                if ($deleted % 10000 === 0 || $deleted === count($toDelete)) {
                    Log::info('SoftDeleteMissingVoters: deleted chunk', ['chunk_size' => $count, 'total' => $deleted, 'batch_id' => $batchId]);
                }
            }
            $report['deleted'] = $deleted;
            $batch->status = 'soft_deleted';
        } else {
            $batch->status = 'soft_delete_preview';
        }

        // Clean up temp table
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_imported_voters');

        $existingReport = [];
        if (! empty($batch->report) && is_string($batch->report)) {
            $decoded = json_decode($batch->report, true);
            if (is_array($decoded)) {
                $existingReport = $decoded;
            }
        } elseif (is_array($batch->report)) {
            $existingReport = $batch->report;
        }
        $encodedReport = json_encode(array_merge($existingReport, ['soft_delete' => $report]));
        $batch->report = is_string($encodedReport) ? $encodedReport : null;
        $batch->save();

        Log::info('SoftDeleteMissingVoters completed', ['batch_id' => $batchId, 'report' => $report]);
    }
}
