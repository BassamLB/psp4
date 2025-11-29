<?php

namespace Database\Seeders;

use App\Models\ElectoralDistrict;
use Illuminate\Database\Seeder;

class ElectoralDistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $electoralDistricts = [
            ['id' => 1, 'name' => 'بيروت الأولى', 'total_seats' => 8, 'is_tracked' => false],
            ['id' => 2, 'name' => 'بيروت الثانية', 'total_seats' => 11, 'is_tracked' => true],
            ['id' => 3, 'name' => 'جبل لبنان الأولى', 'total_seats' => 8, 'is_tracked' => false],
            ['id' => 4, 'name' => 'جبل لبنان الثانية', 'total_seats' => 8, 'is_tracked' => false],
            ['id' => 5, 'name' => 'جبل لبنان الثالثة', 'total_seats' => 6, 'is_tracked' => true],
            ['id' => 6, 'name' => 'جبل لبنان الرابعة', 'total_seats' => 13, 'is_tracked' => true],
            ['id' => 7, 'name' => 'الشمال الأولى', 'total_seats' => 7, 'is_tracked' => false],
            ['id' => 8, 'name' => 'الشمال الثانية', 'total_seats' => 11, 'is_tracked' => false],
            ['id' => 9, 'name' => 'الشمال الثالثة', 'total_seats' => 10, 'is_tracked' => false],
            ['id' => 10, 'name' => 'الجنوب الأولى', 'total_seats' => 5, 'is_tracked' => false],
            ['id' => 11, 'name' => 'الجنوب الثانية', 'total_seats' => 7, 'is_tracked' => false],
            ['id' => 12, 'name' => 'الجنوب الثالثة', 'total_seats' => 11, 'is_tracked' => true],
            ['id' => 13, 'name' => 'البقاع الأولى', 'total_seats' => 7, 'is_tracked' => false],
            ['id' => 14, 'name' => 'البقاع الثانية', 'total_seats' => 6, 'is_tracked' => true],
            ['id' => 15, 'name' => 'البقاع الثالثة', 'total_seats' => 10, 'is_tracked' => false],
        ];

        ElectoralDistrict::upsert($electoralDistricts, ['id'], ['name', 'total_seats', 'is_tracked']);
    }
}
