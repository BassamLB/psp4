<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a user with region assignments', function () {
    // Create roles
    $adminRole = Role::create(['name' => 'مدير']);
    $testRole = Role::create(['name' => 'معدل معلومات']);

    // Create admin user
    $adminUser = User::factory()->create(['role_id' => $adminRole->id]);

    // Get form data for creating user
    $response = $this->actingAs($adminUser)->get('/admin/users/create');
    $response->assertOk();

    // Test with empty region_ids (should be valid)
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_id' => $testRole->id,
        'mobile_number' => '0501234567',
        'region_ids' => [],
        'is_active' => true,
        'is_allowed' => false,
        'is_blocked' => false,
    ];

    $response = $this->actingAs($adminUser)->post('/admin/users', $userData);

    // Should redirect on success
    $response->assertRedirect('/admin/users');

    // Verify user was created with empty region_ids
    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test User');
    expect($user->region_ids)->toBeArray();
    expect($user->region_ids)->toBeEmpty();
});
it('can update a user with region assignments', function () {
    // Create roles
    $adminRole = Role::create(['name' => 'مدير']);
    $testRole = Role::create(['name' => 'معدل معلومات']);

    // Create admin user
    $adminUser = User::factory()->create(['role_id' => $adminRole->id]);

    // Create user with initial empty regions
    $user = User::factory()->create([
        'role_id' => $testRole->id,
        'region_ids' => [],
    ]);

    // Get form data for editing user
    $response = $this->actingAs($adminUser)->get("/admin/users/{$user->id}/edit");
    $response->assertOk();

    // Update user with empty region assignments (should work)
    $updateData = [
        'name' => 'Updated User',
        'email' => $user->email,
        'role_id' => $testRole->id,
        'mobile_number' => '0509876543',
        'region_ids' => [],
        'is_active' => true,
        'is_allowed' => true,
        'is_blocked' => false,
    ];

    $response = $this->actingAs($adminUser)->put("/admin/users/{$user->id}", $updateData);

    // Should redirect on success
    $response->assertRedirect('/admin/users');

    // Verify user was updated
    $user->refresh();
    expect($user->name)->toBe('Updated User');
    expect($user->region_ids)->toBeArray();
    expect($user->region_ids)->toBeEmpty();
});

it('validates region_ids must be an array', function () {
    // Create roles
    $adminRole = Role::create(['name' => 'مدير']);
    $testRole = Role::create(['name' => 'معدل معلومات']);

    // Create admin user
    $adminUser = User::factory()->create(['role_id' => $adminRole->id]);

    // Try to create user with invalid region_ids format
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_id' => $testRole->id,
        'mobile_number' => '0501234567',
        'region_ids' => 'not_an_array', // Invalid format
        'is_active' => true,
        'is_allowed' => false,
        'is_blocked' => false,
    ];

    $response = $this->actingAs($adminUser)->post('/admin/users', $userData);

    // Should return validation errors for invalid region_ids format
    $response->assertSessionHasErrors(['region_ids']);
});
