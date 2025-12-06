<?php

namespace Database\Seeders;

use App\Models\Profession;
use Illuminate\Database\Seeder;

class ProfessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $professions = [
            'رئيس بلدية',
            'نائب رئيس بلدية',
            'مختار',
            'دكتور',
            'طبيب أسنان',
            'طبيب بيطري',
            'مهندس',
            'مهندس معماري',
            'محامي',
            'معلم',
            'طوبوغراف',
            'أستاذ جامعي',
            'فني مختبر',
            'صيدلي',
            'محاسب',
            'ممرض',
            'مطور برمجيات',
            'مصمم جرافيك',
            'كاتب',
            'صحفي',
            'مترجم',
            'عامل',
            'موظف حكومي',
            'رجل أعمال',
            'متقاعد',
            'طالب',
            'أخرى',
        ];

        foreach ($professions as $profession) {
            Profession::create(['name' => $profession]);
        }

        $this->command->info('Successfully seeded professions!');
    }
}
