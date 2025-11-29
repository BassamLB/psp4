<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'مدير'], ['name' => 'وكيل داخلية'], ['name' => 'معتمد'], ['name' => 'مدير فرع'], ['name' => 'مندوب استقبال'], ['name' => 'مندوب صندوق'],
        ];
        // upsert by unique 'name' column to avoid duplicates
        Role::upsert($roles, ['name']);
    }
}
