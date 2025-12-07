<?php

use App\Jobs\AssignFamiliesToVoters;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $adminRole = Role::create(['name' => 'مدير']);
    test()->admin = User::factory()->create(['role_id' => $adminRole->id]);
});

it('can view family assignment page as admin', function () {
    $response = $this->actingAs(test()->admin)
        ->get('/admin/family-assignment');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/FamilyAssignment/Index')
        ->has('stats')
        ->has('sample_families')
    );
});

it('shows correct statistics on family assignment page', function () {
    $response = $this->actingAs(test()->admin)
        ->get('/admin/family-assignment');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('stats', fn ($stats) => $stats
            ->has('total_voters')
            ->has('voters_with_family')
            ->has('voters_without_family')
            ->has('total_families')
            ->has('percentage_assigned')
        )
    );
});

it('can dispatch incremental family assignment job', function () {
    Queue::fake();

    $response = $this->actingAs(test()->admin)
        ->post('/admin/family-assignment/assign');

    $response->assertRedirect('/admin/family-assignment');
    $response->assertSessionHas('success');

    Queue::assertPushed(AssignFamiliesToVoters::class, function ($job) {
        return $job->options['incremental'] === true;
    });
});

it('can dispatch full family assignment job', function () {
    Queue::fake();

    $response = $this->actingAs(test()->admin)
        ->post('/admin/family-assignment/assign-all');

    $response->assertRedirect('/admin/family-assignment');
    $response->assertSessionHas('success');

    Queue::assertPushed(AssignFamiliesToVoters::class, function ($job) {
        return empty($job->options);
    });
});

it('requires admin access for family assignment page', function () {
    $userRole = Role::create(['name' => 'مستخدم']);
    $user = User::factory()->create(['role_id' => $userRole->id]);

    $response = $this->actingAs($user)
        ->get('/admin/family-assignment');

    $response->assertForbidden();
});

it('requires admin access to trigger family assignment', function () {
    $userRole = Role::create(['name' => 'مستخدم']);
    $user = User::factory()->create(['role_id' => $userRole->id]);

    $response = $this->actingAs($user)
        ->post('/admin/family-assignment/assign');

    $response->assertForbidden();
});
