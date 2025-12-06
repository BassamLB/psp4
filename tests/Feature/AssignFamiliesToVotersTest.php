<?php

use App\Jobs\AssignFamiliesToVoters;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable foreign key constraints
    Schema::disableForeignKeyConstraints();
});

afterEach(function () {
    // Re-enable foreign key constraints
    Schema::enableForeignKeyConstraints();
});

test('it creates families from voters', function () {
    // Create test voters with same family name
    Voter::factory()->create([
        'family_name' => 'الأحمد',
        'sijil_number' => 12345,
        'town_id' => 1,
        'sect_id' => 1,
    ]);

    Voter::factory()->create([
        'family_name' => 'الأحمد',
        'sijil_number' => 12345,
        'town_id' => 1,
        'sect_id' => 1,
    ]);

    AssignFamiliesToVoters::dispatchSync();

    $familyCount = DB::table('families')->count();
    expect($familyCount)->toBeGreaterThan(0);
});

test('it normalizes arabic names correctly', function () {
    // Create voters with different alef variants
    Voter::factory()->create([
        'family_name' => 'أحمد',
        'sijil_number' => 12345,
        'town_id' => 1,
        'sect_id' => 1,
    ]);

    Voter::factory()->create([
        'family_name' => 'احمد',
        'sijil_number' => 12345,
        'town_id' => 1,
        'sect_id' => 1,
    ]);

    AssignFamiliesToVoters::dispatchSync();

    // Both should be grouped under same canonical family
    $familyCount = DB::table('families')
        ->where('canonical_name', 'احمد')
        ->count();

    expect($familyCount)->toBe(1);
});

test('it assigns family_id to voters', function () {
    $voter = Voter::factory()->create([
        'family_name' => 'الحسن',
        'sijil_number' => 67890,
        'town_id' => 2,
        'sect_id' => 2,
    ]);

    expect($voter->family_id)->toBeNull();

    AssignFamiliesToVoters::dispatchSync();

    $voter->refresh();
    expect($voter->family_id)->not->toBeNull();
});

test('it handles incremental mode', function () {
    // Create first batch of voters
    Voter::factory()->count(3)->create([
        'family_name' => 'العلي',
        'sijil_number' => 11111,
        'town_id' => 3,
        'sect_id' => 3,
    ]);

    AssignFamiliesToVoters::dispatchSync();

    // Create second batch
    $newVoter = Voter::factory()->create([
        'family_name' => 'الجديد',
        'sijil_number' => 22222,
        'town_id' => 4,
        'sect_id' => 4,
    ]);

    expect($newVoter->family_id)->toBeNull();

    // Run in incremental mode (only process voters without family_id)
    AssignFamiliesToVoters::dispatchSync(['incremental' => true]);

    $newVoter->refresh();
    expect($newVoter->family_id)->not->toBeNull();
});

test('it groups voters by canonical name, town, sect, and sijil', function () {
    // Same canonical but different towns - should create different families
    Voter::factory()->create([
        'family_name' => 'السيد',
        'sijil_number' => 33333,
        'town_id' => 1,
        'sect_id' => 1,
    ]);

    Voter::factory()->create([
        'family_name' => 'السيد',
        'sijil_number' => 33333,
        'town_id' => 2,
        'sect_id' => 1,
    ]);

    AssignFamiliesToVoters::dispatchSync();

    $familyCount = DB::table('families')
        ->where('canonical_name', 'السيد')
        ->count();

    expect($familyCount)->toBe(2);
});

test('it handles voters without family names', function () {
    Voter::factory()->create([
        'family_name' => null,
        'sijil_number' => 44444,
    ]);

    Voter::factory()->create([
        'family_name' => '',
        'sijil_number' => 55555,
    ]);

    AssignFamiliesToVoters::dispatchSync();

    $voters = Voter::whereIn('sijil_number', [44444, 55555])->get();

    foreach ($voters as $voter) {
        expect($voter->family_id)->toBeNull();
    }
});
