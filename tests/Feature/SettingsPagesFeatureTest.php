<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_professions_page_loads()
    {
        $role = Role::create(['name' => 'مدير']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/settings/professions')
            ->assertStatus(200)
            ->assertSee('admin/Settings/Professions');
    }

    public function test_roles_page_loads()
    {
        $role = Role::first() ?? Role::create(['name' => 'مدير']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/settings/roles')
            ->assertStatus(200)
            ->assertSee('admin/Settings/Roles');
    }

    public function test_belongs_page_loads()
    {
        $role = Role::first() ?? Role::create(['name' => 'مدير']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/settings/belongs')
            ->assertStatus(200)
            ->assertSee('admin/Settings/Belongs');
    }

    public function test_genders_page_loads()
    {
        $role = Role::first() ?? Role::create(['name' => 'مدير']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/settings/genders')
            ->assertStatus(200)
            ->assertSee('admin/Settings/Genders');
    }

    public function test_sects_page_loads()
    {
        $role = Role::first() ?? Role::create(['name' => 'مدير']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get('/admin/settings/sects')
            ->assertStatus(200)
            ->assertSee('admin/Settings/Sects');
    }
}
