<?php

use App\Models\User;
use Illuminate\Support\Str;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register with valid registration code', function () {
    // Create an invited user with registration code
    $user = User::create([
        'email' => 'invited@example.com',
        'registration_code' => Str::random(32),
        'password' => null,
        'name' => 'Invited User', // Temp name until registration
        'is_active' => false,
        'is_allowed' => false,
    ]);

    $response = $this->post(route('register.store'), [
        'registration_code' => $user->registration_code,
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    // Verify user was updated
    $user->refresh();
    expect($user->name)->toBe('Invited User'); // Name should remain unchanged
    expect($user->password)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->is_allowed)->toBeFalse(); // Should require admin approval
});

test('users cannot register with invalid registration code', function () {
    $response = $this->post(route('register.store'), [
        'registration_code' => 'invalid-code',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('registration_code');
});

test('users cannot register with already used registration code', function () {
    // Create a user that has already completed registration
    $user = User::factory()->create([
        'email' => 'registered@example.com',
        'registration_code' => Str::random(32),
        'password' => 'existing-password',
        'name' => 'Existing User',
        'is_active' => true,
    ]);

    $response = $this->post(route('register.store'), [
        'registration_code' => $user->registration_code,
        'email' => 'registered@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('registration_code');
});

test('users cannot register with mismatched email and registration code', function () {
    // Create an invited user
    $user = User::create([
        'email' => 'invited@example.com',
        'registration_code' => Str::random(32),
        'password' => null,
        'name' => 'Invited User',
        'is_active' => false,
    ]);

    $response = $this->post(route('register.store'), [
        'registration_code' => $user->registration_code,
        'email' => 'different@example.com', // Wrong email
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
