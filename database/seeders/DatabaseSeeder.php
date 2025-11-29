<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first
        $this->call(RoleSeeder::class);

        // Create admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@psp.org',
            'is_allowed' => true,
            'is_blocked' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        User::factory()->box()->create([
            'name' => 'Box User',
            'email' => 'bassam1@psp.org',
            'is_allowed' => true,
            'is_blocked' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        User::factory()->box()->create([
            'name' => 'Box User2',
            'email' => 'bassam2@psp.org',
            'is_allowed' => true,
            'is_blocked' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->call(GenderSeeder::class);
        $this->call(SectSeeder::class);
        $this->call(ProfessionSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(ElectoralDistrictSeeder::class);
        $this->call(DistrictSeeder::class);
        // Ensure agencies, delegates and branches are seeded before towns so branch_id can be referenced
        $this->call(AgencyDelegateBranchSeeder::class);
        $this->call(TownSeeder::class);
        $this->call(PollingStationSeeder::class);
        $this->call(ChoufElectoralListsSeeder::class);
    }
}
