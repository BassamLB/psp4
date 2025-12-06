<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    // second-level setup if needed later
});

test('non-admins cannot view settings index routes', function (string $route) {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route($route));

    $response->assertForbidden();
})->with([
    'roles' => 'admin.settings.roles.index',
    'belongs' => 'admin.settings.belongs.index',
    'professions' => 'admin.settings.professions.index',
    'genders' => 'admin.settings.genders.index',
    'sects' => 'admin.settings.sects.index',
]);

test('admins can view settings index routes', function (string $route) {
    Role::create(['name' => 'مدير']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $response = $this->get(route($route));

    $response->assertStatus(200);
})->with([
    'roles' => 'admin.settings.roles.index',
    'belongs' => 'admin.settings.belongs.index',
    'professions' => 'admin.settings.professions.index',
    'genders' => 'admin.settings.genders.index',
    'sects' => 'admin.settings.sects.index',
]);

test('non-admins cannot store settings resources', function (string $route) {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route($route), ['name' => 'Acme']);

    $response->assertForbidden();
})->with([
    'roles' => 'admin.settings.roles.store',
    'belongs' => 'admin.settings.belongs.store',
    'professions' => 'admin.settings.professions.store',
    'genders' => 'admin.settings.genders.store',
    'sects' => 'admin.settings.sects.store',
]);

test('admins can create settings resources', function (string $route) {
    Role::create(['name' => 'مدير']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $response = $this->post(route($route), ['name' => 'Acme']);

    $response->assertRedirect();
    $response->assertSessionHas('success');
})->with([
    'roles' => 'admin.settings.roles.store',
    'belongs' => 'admin.settings.belongs.store',
    'professions' => 'admin.settings.professions.store',
    'genders' => 'admin.settings.genders.store',
    'sects' => 'admin.settings.sects.store',
]);

test('admins can update settings resources and receive a success flash', function (string $base) {
    Role::create(['name' => 'مدير']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    // create a record to update depending on the base name
    $modelClass = match ($base) {
        'roles' => \App\Models\Role::class,
        'belongs' => \App\Models\Belong::class,
        'professions' => \App\Models\Profession::class,
        'genders' => \App\Models\Gender::class,
        'sects' => \App\Models\Sect::class,
    };

    $item = $modelClass::create(['name' => 'Original']);

    $routeName = "admin.settings.{$base}.update";

    $response = $this->put(route($routeName, [$item->id]), ['name' => 'Updated']);

    $response->assertRedirect();
    $response->assertSessionHas('success');
})->with(['roles', 'belongs', 'professions', 'genders', 'sects']);

test('admins can delete settings resources and receive a success flash', function (string $base) {
    Role::create(['name' => 'مدير']);
    $user = User::factory()->admin()->create();

    $this->actingAs($user);

    $modelClass = match ($base) {
        'roles' => \App\Models\Role::class,
        'belongs' => \App\Models\Belong::class,
        'professions' => \App\Models\Profession::class,
        'genders' => \App\Models\Gender::class,
        'sects' => \App\Models\Sect::class,
    };

    $item = $modelClass::create(['name' => 'To Delete']);

    $routeName = "admin.settings.{$base}.destroy";

    $response = $this->delete(route($routeName, [$item->id]));

    $response->assertRedirect();
    $response->assertSessionHas('success');
})->with(['roles', 'belongs', 'professions', 'genders', 'sects']);
