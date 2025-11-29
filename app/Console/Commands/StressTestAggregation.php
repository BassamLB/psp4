<?php

namespace App\Console\Commands;

use App\Jobs\AggregateStationResults;
use App\Jobs\ProcessBallotEntry;
use App\Models\Candidate;
use App\Models\ElectoralList;
use App\Models\PollingStation;
use Illuminate\Console\Command;

class StressTestAggregation extends Command
{
    protected $signature = 'stress:aggregation
                            {--station= : Polling station ID}
                            {--entries=1000 : Number of ballot entry jobs to dispatch}
                            {--user=1 : User ID to attribute entries to}
                            {--direct-aggregates=0 : Dispatch aggregate jobs directly (count)}
                            {--delay=0 : Delay seconds between dispatch batches}';

    protected $description = 'Stress test aggregation by dispatching many ProcessBallotEntry jobs (and optional direct aggregate jobs)';

    public function handle(): int
    {
        $stationId = (int) $this->option('station');
        $entries = (int) $this->option('entries');
        $userId = (int) $this->option('user');
        $directAggregates = (int) $this->option('direct-aggregates');
        $delay = (int) $this->option('delay');

        if (! $stationId) {
            $this->error('Please provide --station=ID');

            return 1;
        }

        /** @var PollingStation|null $station */
        $station = PollingStation::find($stationId);

        if (! $station) {
            $this->error("Polling station {$stationId} not found.");

            return 1;
        }

        $this->info("Starting stress test: station={$stationId} entries={$entries} user={$userId}");

        $listIds = ElectoralList::where('electoral_district_id', $station->town->district->electoral_district_id)
            ->pluck('id')
            ->toArray();

        $candidateIds = Candidate::whereIn('list_id', $listIds)->pluck('id')->toArray();

        $start = microtime(true);

        for ($i = 0; $i < $entries; $i++) {
            // Randomize ballot type roughly 70% list, 30% preferential
            $isPreferential = (random_int(1, 100) <= 30);

            $data = [];

            if ($isPreferential && ! empty($candidateIds)) {
                $candidateId = $candidateIds[array_rand($candidateIds)];
                // The entry may omit list_id for preferential if candidate_id implies list
                $data['candidate_id'] = $candidateId;
                // Optionally set list_id to candidate's list via DB lookup, but keep simple
                $data['ballot_type'] = 'valid_preferential';
            } else {
                if (! empty($listIds)) {
                    $listId = $listIds[array_rand($listIds)];
                    $data['list_id'] = $listId;
                }
                $data['ballot_type'] = 'valid_list';
            }

            // Minimal metadata
            $data['metadata'] = ['stress_test' => true, 'index' => $i];

            ProcessBallotEntry::dispatch($data, $stationId, $userId)
                ->onQueue('ballot-entry');

            if ($delay > 0 && $i % 200 === 0) {
                sleep($delay);
            }
        }

        $duration = microtime(true) - $start;
        $this->info("Dispatched {$entries} ProcessBallotEntry jobs in {$duration} seconds.");

        if ($directAggregates > 0) {
            $this->info("Dispatching {$directAggregates} direct AggregateStationResults jobs (station {$stationId})");
            for ($i = 0; $i < $directAggregates; $i++) {
                AggregateStationResults::dispatch($stationId)->onQueue('aggregation');
            }
        }

        $this->info('Stress test dispatch complete. Start workers to process the queues (e.g. php artisan queue:work --queue=ballot-entry,aggregation)');

        return 0;
    }
}
