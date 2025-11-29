<?php

namespace Database\Seeders;

use App\Models\Sect;
use Illuminate\Database\Seeder;

class SectSeeder extends Seeder
{
    public function run(): void
    {
        $sects = [
            ['id' => 1, 'name' => 'درزي'],
            ['id' => 2, 'name' => 'ارمن ارثوذكس'],
            ['id' => 3, 'name' => 'ارمن كاثوليك'],
            ['id' => 4, 'name' => 'اشوري ارثوذكس'],
            ['id' => 5, 'name' => 'انجيلي'],
            ['id' => 6, 'name' => 'روم ارثوذكس'],
            ['id' => 7, 'name' => 'روم كاثوليك'],
            ['id' => 8, 'name' => 'سريان ارثوذكس'],
            ['id' => 9, 'name' => 'سريان كاثوليك'],
            ['id' => 10, 'name' => 'سني'],
            ['id' => 11, 'name' => 'شيعي'],
            ['id' => 12, 'name' => 'علوي'],
            ['id' => 13, 'name' => 'قبطي ارثوذكس'],
            ['id' => 14, 'name' => 'كلدان'],
            ['id' => 15, 'name' => 'كلدان ارثوذكس'],
            ['id' => 16, 'name' => 'كلدان كاثوليك'],
            ['id' => 17, 'name' => 'لاتين'],
            ['id' => 18, 'name' => 'ماروني'],
            ['id' => 19, 'name' => 'مختلط'],
            ['id' => 20, 'name' => 'إسرائيلي'],
        ];

        // Insert or update by `id` in a single query
        Sect::upsert($sects, ['id'], ['name']);
    }
}
