<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('user with box role can login and redirect to assigned station', function () {
    $user = User::where('email', 'bassam@psp.local')->first();

    // If the test DB doesn't have the expected seeded user, create the minimal
    // records required so the test can run in isolation. If the user exists but
    // is missing required attributes, update them.
    if (is_null($user)) {
        $role = \App\Models\Role::firstOrCreate(['name' => 'مندوب صندوق']);

        $user = User::create([
            'name' => 'Box User',
            'email' => 'bassam1@psp.org',
            'email_verified_at' => now(),
            'is_allowed' => true,
            'role_id' => $role->id,
        ]);

        // Create minimal location and polling station chain
        $electoralDistrict = \App\Models\ElectoralDistrict::firstOrCreate([
            'name' => 'Test District',
        ], ['total_seats' => 1]);

        $district = \App\Models\District::firstOrCreate([
            'name' => 'Test Subdistrict',
        ], ['electoral_district_id' => $electoralDistrict->id]);

        $town = \App\Models\Town::firstOrCreate([
            'name' => 'Test Town',
        ], ['district_id' => $district->id]);

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

        // Assign the user to the polling station as an active box delegate (counter)
        \App\Models\StationUserAssignment::create([
            'polling_station_id' => $pollingStation->id,
            'user_id' => $user->id,
            'role' => 'counter',
            'assigned_at' => now(),
            'assigned_by' => $user->id,
            'is_active' => true,
        ]);
    } else {
        // Ensure user has the attributes the test expects
        $updated = false;
        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $updated = true;
        }

        if (! $user->is_allowed) {
            $user->is_allowed = true;
            $updated = true;
        }

        // Ensure role is box delegate
        $role = \App\Models\Role::firstOrCreate(['name' => 'مندوب صندوق']);
        if ($user->role_id !== $role->id) {
            $user->role_id = $role->id;
            $updated = true;
        }

        if ($updated) {
            $user->save();
        }

        // Ensure there is an active assignment
        $assignment = $user->stationAssignments()
            ->where('is_active', true)
            ->where('role', 'counter')
            ->first();

        if (is_null($assignment)) {
            // Create minimal polling station chain if necessary
            $electoralDistrict = \App\Models\ElectoralDistrict::firstOrCreate([
                'name' => 'Test District',
            ], ['total_seats' => 1]);

            $district = \App\Models\District::firstOrCreate([
                'name' => 'Test Subdistrict',
            ], ['electoral_district_id' => $electoralDistrict->id]);

            $town = \App\Models\Town::firstOrCreate([
                'name' => 'Test Town',
            ], ['district_id' => $district->id]);

            $election = \App\Models\Election::firstOrCreate(['name' => 'Test Election'], ['election_date' => now()->toDateString()]);

            $pollingStation = \App\Models\PollingStation::firstOrCreate([
                'election_id' => $election->id,
                'town_id' => $town->id,
                'station_number' => 1,
            ], ['location' => 'Test Location']);

            \App\Models\StationUserAssignment::create([
                'polling_station_id' => $pollingStation->id,
                'user_id' => $user->id,
                'role' => 'counter',
                'assigned_at' => now(),
                'assigned_by' => $user->id,
                'is_active' => true,
            ]);
        }
    }

    // Refresh the model from the database to pick up any changes made above
    $user = $user->fresh();

    // Ensure email_verified_at is set for the assertion/login
    if (is_null($user->email_verified_at)) {
        $user->email_verified_at = now();
        $user->save();
        $user = $user->fresh();
    }

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->is_allowed)->toBeTrue()
        ->and($user->isBox())->toBeTrue();

    $assignment = $user->stationAssignments()
        ->where('is_active', true)
        ->where('role', 'counter')
        ->first();

    expect($assignment)->not->toBeNull();

    // Login the user
    actingAs($user);

    // Visit dashboard which should redirect to ballots.entry
    $response = get('/dashboard');

    $response->assertRedirect(route('ballots.entry', ['station' => $assignment->polling_station_id]));
});

test('ballots entry page is accessible for assigned box delegate', function () {
    $user = User::where('email', 'bassam1@psp.org')->first();

    if (is_null($user)) {
        $role = \App\Models\Role::firstOrCreate(['name' => 'مندوب صندوق']);

        $user = User::create([
            'name' => 'Box User',
            'email' => 'bassam1@psp.org',
            'is_allowed' => true,
            'role_id' => $role->id,
        ]);
        // `email_verified_at` isn't mass-assignable on User; set directly and save.
        $user->email_verified_at = now();
        $user->save();

        $electoralDistrict = \App\Models\ElectoralDistrict::firstOrCreate([
            'name' => 'Test District',
        ], ['total_seats' => 1]);

        $district = \App\Models\District::firstOrCreate([
            'name' => 'Test Subdistrict',
        ], ['electoral_district_id' => $electoralDistrict->id]);

        $town = \App\Models\Town::firstOrCreate([
            'name' => 'Test Town',
        ], ['district_id' => $district->id]);

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

        \App\Models\StationUserAssignment::create([
            'polling_station_id' => $pollingStation->id,
            'user_id' => $user->id,
            'role' => 'counter',
            'assigned_at' => now(),
            'assigned_by' => $user->id,
            'is_active' => true,
        ]);
    }

    $assignment = $user->stationAssignments()
        ->where('is_active', true)
        ->where('role', 'counter')
        ->first();

    actingAs($user);

    $response = get(route('ballots.entry', ['station' => $assignment->polling_station_id]));

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Ballots/EntryGrid')
            ->has('station')
            ->has('lists')
        );
});
