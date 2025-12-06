<?php

use App\Models\User;

uses(Tests\TestCase::class)->in('Browser');

beforeEach(function () {
    // Create an in-memory user instance and authenticate it for browser tests.
    $user = new User(['name' => 'Test User', 'email' => 'test@example.com']);
    $user->id = 1;
    $this->actingAs($user);
});

it('loads the professions page without JS errors', function () {
    $page = visit('/admin/settings/professions');

    $page->assertSee('المهن')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the roles page without JS errors', function () {
    $page = visit('/admin/settings/roles');

    $page->assertSee('الأدوار')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the belongs page without JS errors', function () {
    $page = visit('/admin/settings/belongs');

    $page->assertSee('الانتماءات')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the genders page without JS errors', function () {
    $page = visit('/admin/settings/genders');

    $page->assertSee('الجنس')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('loads the sects page without JS errors', function () {
    $page = visit('/admin/settings/sects');

    $page->assertSee('الطوائف')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
