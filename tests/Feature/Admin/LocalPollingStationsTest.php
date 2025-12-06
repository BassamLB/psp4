<?php

use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Role;
use App\Models\User;

test('non-admins cannot view local polling station show route', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $election = new Election;
    $election->name = 'Test Election';
    $election->election_date = now()->toDateString();
    $election->save();

    // Create the necessary location hierarchy for town -> district -> electoral district
    $electoral = \App\Models\ElectoralDistrict::create(['name' => 'Test ED', 'total_seats' => 1]);
    $district = \App\Models\District::create(['name' => 'Test District', 'electoral_district_id' => $electoral->id]);
    $town = \App\Models\Town::create(['name' => 'Test Town', 'district_id' => $district->id]);

    $station = PollingStation::create([
        'town_id' => $town->id,
        'election_id' => $election->id,
        'station_number' => 123,
        'location' => 'Test Location',
    ]);

    $response = $this->get(route('admin.local-polling-stations.show', [$station->id]));

    $response->assertForbidden();
});

test('admins are redirected from show to edit for local polling stations', function () {
    Role::create(['name' => 'مدير']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $election = new Election;
    $election->name = 'Test Election 2';
    $election->election_date = now()->toDateString();
    $election->save();

    // Create the necessary location hierarchy for town -> district -> electoral district
    $electoral = \App\Models\ElectoralDistrict::create(['name' => 'Test ED 2', 'total_seats' => 1]);
    $district = \App\Models\District::create(['name' => 'Test District 2', 'electoral_district_id' => $electoral->id]);
    $town = \App\Models\Town::create(['name' => 'Test Town 2', 'district_id' => $district->id]);

    $station = PollingStation::create([
        'town_id' => $town->id,
        'election_id' => $election->id,
        'station_number' => 123,
        'location' => 'Test Location',
    ]);

    $response = $this->get(route('admin.local-polling-stations.show', [$station->id]));

    $response->assertRedirect(route('admin.local-polling-stations.edit', [$station->id]));
});
