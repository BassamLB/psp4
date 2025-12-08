<?php

namespace App\Jobs;

use App\Events\StationResultsUpdated;
use App\Models\StationAggregate;
use App\Models\StationSummary;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateStationResults implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $maxExceptions = 2;

    public array $backoff = [10, 30, 60];

    private const LOCK_TTL_BUFFER = 5;

    private const LOCK_WAIT_SECONDS = 5;

    private const CACHE_TTL_MINUTES = 5;

    public function __construct(public int $pollingStationId)
    {
        $this->onQueue('aggregation');
    }

    public function handle(): void
    {
        $lock = $this->acquireLock();

        if (! $lock) {
            $this->handleLockFailure();

            return;
        }

        try {
            $this->performAggregation();
        } catch (\Exception $e) {
            $this->handleAggregationFailure($e);
            throw $e;
        } finally {
            $this->releaseLock($lock);
        }
    }

    protected function acquireLock()
    {
        $lockKey = "aggregate:station:{$this->pollingStationId}";
        $lockSeconds = $this->timeout + self::LOCK_TTL_BUFFER;
        $lock = Cache::lock($lockKey, $lockSeconds);

        return $lock->block(self::LOCK_WAIT_SECONDS) ? $lock : null;
    }

    protected function handleLockFailure(): void
    {
        Log::info('Aggregation already in progress for station', [
            'station_id' => $this->pollingStationId,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);

        try {
            $this->release(3);
        } catch (\Throwable $e) {
            self::dispatch($this->pollingStationId)
                ->delay(now()->addSeconds(3))
                ->onQueue('aggregation');
        }
    }

    protected function performAggregation(): void
    {
        Log::info('Starting aggregation for station', [
            'station_id' => $this->pollingStationId,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Compute aggregates outside transaction
        $listCounts = $this->getListCounts();
        $candidateCounts = $this->getCandidateCounts();
        $summaryCounts = $this->getSummaryCounts();

        // Short transaction for writes only
        DB::transaction(function () use ($listCounts, $candidateCounts, $summaryCounts) {
            $this->persistAggregates($listCounts, $candidateCounts);
            $this->updateStationSummary($summaryCounts);
        });

        // Update cache and broadcast after commit
        $this->updateCache();
        $this->broadcastUpdate();

        Log::info('Aggregation completed successfully for station', [
            'station_id' => $this->pollingStationId,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    protected function handleAggregationFailure(\Exception $e): void
    {
        Log::error('Failed to aggregate station results', [
            'station_id' => $this->pollingStationId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    protected function releaseLock($lock): void
    {
        try {
            $lock->release();
        } catch (\Throwable $e) {
            Log::warning('Failed to release aggregation lock', [
                'station_id' => $this->pollingStationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function broadcastUpdate(): void
    {
        Log::info('Broadcasting results update for station', [
            'station_id' => $this->pollingStationId,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);

        broadcast(new StationResultsUpdated($this->pollingStationId));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Aggregation job failed permanently', [
            'station_id' => $this->pollingStationId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function getListCounts(): \Illuminate\Support\Collection
    {
        return DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->whereIn('ballot_type', ['valid_list', 'valid_preferential'])
            ->whereNotNull('list_id')
            ->groupBy('list_id')
            ->select('list_id', DB::raw('count(*) as vote_count'))
            ->get()
            ->map(fn ($r) => new \App\ValueObjects\ListVoteCount(
                $r->list_id !== null ? (int) $r->list_id : null,
                (int) $r->vote_count
            ));
    }

    protected function getCandidateCounts(): \Illuminate\Support\Collection
    {
        return DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->where('ballot_type', 'valid_preferential')
            ->whereNotNull('candidate_id')
            ->groupBy('candidate_id')
            ->select('candidate_id', DB::raw('count(*) as vote_count'))
            ->get()
            ->map(fn ($r) => new \App\ValueObjects\CandidateVoteCount(
                $r->candidate_id !== null ? (int) $r->candidate_id : null,
                (int) $r->vote_count
            ));
    }

    protected function getSummaryCounts(): ?object
    {
        $row = DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->selectRaw("
                count(*) as total,
                sum(case when ballot_type = 'valid_list' then 1 else 0 end) as valid_list,
                sum(case when ballot_type = 'valid_preferential' then 1 else 0 end) as valid_preferential,
                sum(case when ballot_type = 'white' then 1 else 0 end) as white,
                sum(case when ballot_type = 'cancelled' then 1 else 0 end) as cancelled,
                min(entered_at) as first_entry,
                max(entered_at) as last_entry
            ")
            ->first();

        if ($row === null) {
            return null;
        }

        return (object) [
            'total' => isset($row->total) ? (int) $row->total : null,
            'valid_list' => isset($row->valid_list) ? (int) $row->valid_list : null,
            'valid_preferential' => isset($row->valid_preferential) ? (int) $row->valid_preferential : null,
            'white' => isset($row->white) ? (int) $row->white : null,
            'cancelled' => isset($row->cancelled) ? (int) $row->cancelled : null,
            'first_entry' => $row->first_entry ?? null,
            'last_entry' => $row->last_entry ?? null,
        ];
    }

    protected function persistAggregates(\Illuminate\Support\Collection $listCounts, \Illuminate\Support\Collection $candidateCounts): void
    {
        $rows = [];
        $now = now();

        foreach ($listCounts as $count) {
            $rows[] = [
                'polling_station_id' => $this->pollingStationId,
                'list_id' => $count->list_id,
                'candidate_id' => null,
                'vote_count' => $count->vote_count,
                'last_updated_at' => $now,
            ];
        }

        foreach ($candidateCounts as $count) {
            $rows[] = [
                'polling_station_id' => $this->pollingStationId,
                'list_id' => null,
                'candidate_id' => $count->candidate_id,
                'vote_count' => $count->vote_count,
                'last_updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('station_aggregates')->upsert(
                $rows,
                ['polling_station_id', 'list_id', 'candidate_id'],
                ['vote_count', 'last_updated_at']
            );
        }
    }

    protected function updateStationSummary(?object $counts = null): void
    {
        if (is_null($counts)) {
            $counts = $this->getSummaryCounts();
        }

        StationSummary::updateOrCreate(
            ['polling_station_id' => $this->pollingStationId],
            [
                'total_ballots_entered' => $counts->total ?? 0,
                'valid_list_votes' => $counts->valid_list ?? 0,
                'valid_preferential_votes' => $counts->valid_preferential ?? 0,
                'white_papers' => $counts->white ?? 0,
                'cancelled_papers' => $counts->cancelled ?? 0,
                'first_entry_at' => $counts->first_entry,
                'last_entry_at' => $counts->last_entry,
            ]
        );
    }

    protected function updateCache(): void
    {
        $aggregates = StationAggregate::where('polling_station_id', $this->pollingStationId)
            ->with(['list:id,name', 'candidate:id,full_name'])
            ->orderByDesc('vote_count')
            ->get()
            ->map(fn ($agg) => [
                'list_id' => $agg->list_id,
                'candidate_id' => $agg->candidate_id,
                'vote_count' => $agg->vote_count,
                'list' => $agg->list ? ['name' => $agg->list->name] : null,
                'candidate' => $agg->candidate ? ['full_name' => $agg->candidate->full_name] : null,
            ])
            ->toArray();

        $summary = StationSummary::where('polling_station_id', $this->pollingStationId)->first();

        $cacheTtl = now()->addMinutes(self::CACHE_TTL_MINUTES);

        Cache::put("station:{$this->pollingStationId}:aggregates", $aggregates, $cacheTtl);

        Cache::put(
            "station:{$this->pollingStationId}:summary",
            $summary ? [
                'total_ballots_entered' => $summary->total_ballots_entered,
                'valid_list_votes' => $summary->valid_list_votes,
                'valid_preferential_votes' => $summary->valid_preferential_votes,
                'white_papers' => $summary->white_papers,
                'cancelled_papers' => $summary->cancelled_papers,
            ] : null,
            $cacheTtl
        );
    }

    public function tags(): array
    {
        return ['aggregation', 'station:'.$this->pollingStationId];
    }

    public function uniqueId(): string
    {
        return 'aggregate-station-'.$this->pollingStationId;
    }
}
