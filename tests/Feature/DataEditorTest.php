<?php

use App\Models\Role;
use App\Models\User;

test('requires data editor role to access dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/data-editor');

    $response->assertForbidden();
});

test('allows data editor to access dashboard', function () {
    // Create data editor role if it doesn't exist
    $role = Role::firstOrCreate(['name' => 'معدل معلومات']);

    $user = User::factory()->create([
        'role_id' => $role->id,
        'region_ids' => json_encode([1, 2, 3]), // Give access to some towns
    ]);

    $response = $this->actingAs($user)->get('/data-editor');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('data-editor/Dashboard')
    );
});
