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

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $pollingStationId)
    {
        $this->onQueue('aggregation');
    }

    public function handle(): void
    {
        $lockKey = "aggregate:station:{$this->pollingStationId}";

        // Acquire a redis-backed lock to prevent concurrent aggregations for the same station.
        // Use a TTL slightly longer than the job timeout to avoid the lock expiring while processing.
        $lockSeconds = $this->timeout + 5;
        // @var \Illuminate\Contracts\Cache\Lock $lock
        $lock = Cache::lock($lockKey, $lockSeconds);

        // Try to acquire the lock, waiting up to 5 seconds.
        if (! $lock->block(5)) {
            Log::info('Aggregation already in progress for station ts='.now()->format('Y-m-d H:i:s.u'), [
                'station_id' => $this->pollingStationId,
            ]);

            // Release this job back onto the queue with a short delay so it retries shortly.
            // Use InteractsWithQueue::release to preserve job metadata and attempt counting.
            try {
                $this->release(3);
            } catch (\Throwable $e) {
                // If release() isn't available in the current runtime, fall back to re-dispatching.
                try {
                    self::dispatch($this->pollingStationId)->delay(now()->addSeconds(3))->onQueue('aggregation');
                } catch (\Throwable $_) {
                    Log::warning('Failed to reschedule aggregation job', ['station_id' => $this->pollingStationId, 'error' => $_->getMessage()]);
                }
            }

            return; // Exit current run; job will retry
        }

        try {
            Log::info('Starting aggregation for station ts='.now()->format('Y-m-d H:i:s.u'), [
                'station_id' => $this->pollingStationId,
            ]);

            // Perform read-heavy aggregation queries outside the transaction to keep the
            // transaction window short. We compute aggregates first, then persist them in
            // a short transaction.
            $listCounts = $this->getListCounts();
            $candidateCounts = $this->getCandidateCounts();
            $summaryCounts = $this->getSummaryCounts();

            DB::transaction(function () use ($listCounts, $candidateCounts, $summaryCounts) {
                $this->persistAggregates($listCounts, $candidateCounts);
                $this->updateStationSummary($summaryCounts);
            });

            // Update cache and broadcast after transaction commits
            $this->updateCache();

            Log::info('Broadcasting results update for station ts='.now()->format('Y-m-d H:i:s.u'), [
                'station_id' => $this->pollingStationId,
            ]);
            broadcast(new StationResultsUpdated($this->pollingStationId));

            Log::info('Aggregation completed successfully for station ts='.now()->format('Y-m-d H:i:s.u'), [
                'station_id' => $this->pollingStationId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to aggregate station results ts='.now()->format('Y-m-d H:i:s.u'), [
                'station_id' => $this->pollingStationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            // Release the redis lock. If the lock has already expired, release() will
            // silently fail — that's acceptable here.
            try {
                $lock->release();
            } catch (\Throwable $releaseException) {
                Log::warning('Failed to release aggregation lock', [
                    'station_id' => $this->pollingStationId,
                    'error' => $releaseException->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Aggregation job failed permanently', [
            'station_id' => $this->pollingStationId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function aggregateListVotes(): void
    {
        // Backward compatible stub: prefer getListCounts() + persistAggregates()
        $listCounts = $this->getListCounts();
        $this->persistAggregates($listCounts, collect());
    }

    protected function aggregateCandidateVotes(): void
    {
        // Backward compatible stub: prefer getCandidateCounts() + persistAggregates()
        $candidateCounts = $this->getCandidateCounts();
        $this->persistAggregates(collect(), $candidateCounts);
    }

    /**
     * Return aggregated list vote counts (read-only, not persisted).
     */
    /**
     * Return a collection of aggregated list vote counts.
     * Each item is an object with `list_id` and `vote_count` properties.
     *
     * @return \Illuminate\Support\Collection<int, \App\ValueObjects\ListVoteCount>
     */
    protected function getListCounts(): \Illuminate\Support\Collection
    {
        // Use a raw query via the query builder so we return a Support\Collection of stdClass
        // rows which we then map into plain objects matching the annotated shape.
        $rows = DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->whereIn('ballot_type', ['valid_list', 'valid_preferential'])
            ->whereNotNull('list_id')
            ->groupBy('list_id')
            ->select('list_id', DB::raw('count(*) as vote_count'))
            ->get()
            ->map(function ($r) {
                return new \App\ValueObjects\ListVoteCount(
                    $r->list_id !== null ? (int) $r->list_id : null,
                    (int) $r->vote_count
                );
            });

        return $rows;
    }

    /**
     * Return aggregated candidate vote counts (read-only, not persisted).
     */
    /**
     * Return a collection of aggregated candidate vote counts.
     * Each item is an object with `candidate_id` and `vote_count` properties.
     *
     * @return \Illuminate\Support\Collection<int, \App\ValueObjects\CandidateVoteCount>
     */
    protected function getCandidateCounts(): \Illuminate\Support\Collection
    {
        $rows = DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->where('ballot_type', 'valid_preferential')
            ->whereNotNull('candidate_id')
            ->groupBy('candidate_id')
            ->select('candidate_id', DB::raw('count(*) as vote_count'))
            ->get()
            ->map(function ($r) {
                return new \App\ValueObjects\CandidateVoteCount(
                    $r->candidate_id !== null ? (int) $r->candidate_id : null,
                    (int) $r->vote_count
                );
            });

        return $rows;
    }

    /**
     * Return aggregated summary row containing computed fields.
     *
     * Returned object shape:
     * object{
     *   total: int|null,
     *   valid_list: int|null,
     *   valid_preferential: int|null,
     *   white: int|null,
     *   cancelled: int|null,
     *   first_entry: string|null,
     *   last_entry: string|null,
     * }
     *
     * @return object{total:int|null, valid_list:int|null, valid_preferential:int|null, white:int|null, cancelled:int|null, first_entry:string|null, last_entry:string|null}|null
     */
    protected function getSummaryCounts(): ?object
    {
        $row = DB::table('ballot_entries')
            ->where('polling_station_id', $this->pollingStationId)
            ->selectRaw(
                "count(*) as total,
                sum(case when ballot_type = 'valid_list' then 1 else 0 end) as valid_list,
                sum(case when ballot_type = 'valid_preferential' then 1 else 0 end) as valid_preferential,
                sum(case when ballot_type = 'white' then 1 else 0 end) as white,
                sum(case when ballot_type = 'cancelled' then 1 else 0 end) as cancelled,
                min(entered_at) as first_entry,
                max(entered_at) as last_entry"
            )
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

    /**
     * Persist aggregates for lists and candidates. Keep writes short to minimize transaction time.
     * Both parameters are Eloquent collections produced by the get*Counts() helpers.
     */
    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $listCounts
     * @param  \Illuminate\Support\Collection<int, mixed>  $candidateCounts
     */
    protected function persistAggregates(\Illuminate\Support\Collection $listCounts, \Illuminate\Support\Collection $candidateCounts): void
    {
        // Build two arrays of rows (lists and candidates) and perform a single upsert per station.
        $rows = [];

        foreach ($listCounts as $count) {
            $rows[] = [
                'polling_station_id' => $this->pollingStationId,
                'list_id' => $count->list_id,
                'candidate_id' => null,
                'vote_count' => $count->vote_count,
                'last_updated_at' => now(),
            ];
        }

        foreach ($candidateCounts as $count) {
            $rows[] = [
                'polling_station_id' => $this->pollingStationId,
                'list_id' => null,
                'candidate_id' => $count->candidate_id,
                'vote_count' => $count->vote_count,
                'last_updated_at' => now(),
            ];
        }

        if (! empty($rows)) {
            // Use a single upsert statement to reduce roundtrips and keep the transaction short.
            DB::table('station_aggregates')->upsert(
                $rows,
                ['polling_station_id', 'list_id', 'candidate_id'],
                ['vote_count', 'last_updated_at']
            );
        }
    }

    /**
     * Persist station summary using the pre-computed counts object.
     */
    /**
     * @param  object{total:int|null, valid_list:int|null, valid_preferential:int|null, white:int|null, cancelled:int|null, first_entry:string|null, last_entry:string|null}|null  $counts
     */
    protected function updateStationSummary(?object $counts = null): void
    {
        if (is_null($counts)) {
            $counts = $this->getSummaryCounts();
        }

        // Coerce aggregated NULLs to sensible defaults (0 for counts)
        $total = $counts->total ?? 0;
        $validList = $counts->valid_list ?? 0;
        $validPref = $counts->valid_preferential ?? 0;
        $white = $counts->white ?? 0;
        $cancelled = $counts->cancelled ?? 0;

        StationSummary::updateOrCreate(
            ['polling_station_id' => $this->pollingStationId],
            [
                'total_ballots_entered' => $total,
                'valid_list_votes' => $validList,
                'valid_preferential_votes' => $validPref,
                'white_papers' => $white,
                'cancelled_papers' => $cancelled,
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
            ->map(function ($agg) {
                return [
                    'list_id' => $agg->list_id,
                    'candidate_id' => $agg->candidate_id,
                    'vote_count' => $agg->vote_count,
                    'list' => $agg->list ? ['name' => $agg->list->name] : null,
                    'candidate' => $agg->candidate ? ['full_name' => $agg->candidate->full_name] : null,
                ];
            })
            ->toArray();

        $summary = StationSummary::where('polling_station_id', $this->pollingStationId)->first();

        Cache::put(
            "station:{$this->pollingStationId}:aggregates",
            $aggregates,
            now()->addMinutes(5)
        );

        Cache::put(
            "station:{$this->pollingStationId}:summary",
            $summary ? [
                'total_ballots_entered' => $summary->total_ballots_entered,
                'valid_list_votes' => $summary->valid_list_votes,
                'valid_preferential_votes' => $summary->valid_preferential_votes,
                'white_papers' => $summary->white_papers,
                'cancelled_papers' => $summary->cancelled_papers,
            ] : null,
            now()->addMinutes(5)
        );
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        return ['aggregation', 'station:'.$this->pollingStationId];
    }

    public function uniqueId(): string
    {
        return 'aggregate-station-'.$this->pollingStationId;
    }
}
