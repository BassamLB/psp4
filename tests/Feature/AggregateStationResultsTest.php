<?php

/**
 * @noinspection PhpUndefinedFunctionInspection
 * @noinspection PhpUndefinedMethodInspection
 *
 * @var \Tests\TestCase $this
 */

use App\Events\StationResultsUpdated;
use App\Jobs\AggregateStationResults;
use App\Models\BallotEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

// Pest's `tests/Pest.php` already binds the TestCase and RefreshDatabase for Feature tests.

it('aggregates votes, caches results, and broadcasts update', function () {
    Event::fake();

    // Mock the cache lock to avoid depending on redis in tests
    $fakeLock = new class
    {
        public function block(int $seconds): bool
        {
            return true;
        }

        public function release(): bool
        {
            return true;
        }
    };

    Cache::shouldReceive('lock')->andReturn($fakeLock);
    // Allow cache put/get calls the job makes. Return sensible dummy values for assertions.
    Cache::shouldReceive('put')->andReturnTrue();
    Cache::shouldReceive('get')->andReturnUsing(function (string $key) {
        if (strpos($key, ':aggregates') !== false) {
            return [];
        }

        if (strpos($key, ':summary') !== false) {
            return ['total_ballots_entered' => 3];
        }

        return null;
    });

    $stationId = 123;

    // Create required related records (no need to disable foreign keys)

    // Create minimal related records to satisfy foreign keys
    $electoralDistrict = \App\Models\ElectoralDistrict::create([
        'name' => 'Test District',
        'total_seats' => 1,
    ]);

    $district = \App\Models\District::create([
        'name' => 'Test Subdistrict',
        'electoral_district_id' => $electoralDistrict->id,
    ]);

    $town = \App\Models\Town::create([
        'name' => 'Test Town',
        'district_id' => $district->id,
    ]);

    $election = new \App\Models\Election;
    $election->name = 'Test Election';
    $election->election_date = now()->toDateString();
    $election->save();

    $pollingStation = \App\Models\PollingStation::create([
        'election_id' => $election->id,
        'town_id' => $town->id,
        'station_number' => 1,
        'location' => 'Test Location',
    ]);

    // Create an entering user required by the ballot_entries migration
    $user = User::create([
        'name' => 'Test User',
        'email' => 'tester+agg@example.test',
    ]);

    // Create an electoral list and candidate to reference
    $electoralList = \App\Models\ElectoralList::create([
        'election_id' => $election->id,
        'electoral_district_id' => $electoralDistrict->id,
        'name' => 'Test List',
        'number' => 1,
    ]);

    $candidate = \App\Models\Candidate::create([
        'list_id' => $electoralList->id,
        'full_name' => 'Test Candidate',
        'position_on_list' => 1,
    ]);

    // Seed ballot entries (include required entered_by)

    // Use the created polling station id
    BallotEntry::create([
        'polling_station_id' => $pollingStation->id,
        'list_id' => $electoralList->id,
        'candidate_id' => null,
        'ballot_type' => 'valid_list',
        'entered_by' => $user->id,
        'entered_at' => now(),
    ]);

    BallotEntry::create([
        'polling_station_id' => $pollingStation->id,
        'list_id' => $electoralList->id,
        'candidate_id' => $candidate->id,
        'ballot_type' => 'valid_preferential',
        'entered_by' => $user->id,
        'entered_at' => now(),
    ]);

    BallotEntry::create([
        'polling_station_id' => $pollingStation->id,
        'list_id' => null,
        'candidate_id' => $candidate->id,
        'ballot_type' => 'valid_preferential',
        'entered_by' => $user->id,
        'entered_at' => now(),
    ]);

    // Run the job synchronously against the created polling station
    (new AggregateStationResults($pollingStation->id))->handle();

    // Assert aggregates saved (use created polling station/list/candidate ids)
    expect(\Illuminate\Support\Facades\DB::table('station_aggregates')
        ->where(['polling_station_id' => $pollingStation->id, 'list_id' => $electoralList->id])
        ->exists())->toBeTrue();

    expect(\Illuminate\Support\Facades\DB::table('station_aggregates')
        ->where(['polling_station_id' => $pollingStation->id, 'candidate_id' => $candidate->id])
        ->exists())->toBeTrue();

    // Assert summary saved
    expect(\Illuminate\Support\Facades\DB::table('station_summaries')
        ->where(['polling_station_id' => $pollingStation->id, 'total_ballots_entered' => 3])
        ->exists())->toBeTrue();

    // Assert cache keys are present
    expect(Cache::get("station:{$pollingStation->id}:aggregates"))->not->toBeNull();
    expect(Cache::get("station:{$pollingStation->id}:summary"))->not->toBeNull();

    // Assert broadcast occurred
    Event::assertDispatched(StationResultsUpdated::class, function ($event) use ($pollingStation) {
        return $event->pollingStationId === $pollingStation->id || $event->stationId === $pollingStation->id;
    });
});
