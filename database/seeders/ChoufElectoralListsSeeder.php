<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectoralDistrict;
use App\Models\ElectoralList;
use App\Models\Sect;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChoufElectoralListsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/RCReports-Chouf.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");

            return;
        }

        // Get first election
        $election = Election::first();
        if (! $election) {
            $this->command->error('No election found. Please run ElectionSeeder first.');

            return;
        }

        // Get Chouf district (جبل لبنان الرابعة)
        $district = ElectoralDistrict::where('name', 'جبل لبنان الرابعة')->first();
        if (! $district) {
            $this->command->error('Electoral district "جبل لبنان الرابعة" not found.');

            return;
        }

        // Get sects
        $sects = Sect::pluck('id', 'name')->toArray();

        DB::beginTransaction();

        try {
            // Define the 7 electoral lists with their candidates from the CSV
            $lists = $this->getElectoralLists();

            foreach ($lists as $listNumber => $listData) {
                //              $this->command->info("Creating list: {$listData['name']}");

                // Create the electoral list
                $list = ElectoralList::create([
                    'election_id' => $election->id,
                    'electoral_district_id' => $district->id,
                    'name' => $listData['name'],
                    'number' => $listNumber,
                    'color' => $listData['color'],
                    'passed_factor' => $listData['passed_factor'],
                ]);

                // Create candidates for this list
                foreach ($listData['candidates'] as $position => $candidateData) {
                    $sectId = isset($candidateData['sect']) && isset($sects[$candidateData['sect']])
                        ? $sects[$candidateData['sect']]
                        : null;

                    Candidate::create([
                        'list_id' => $list->id,
                        'full_name' => $candidateData['name'],
                        'position_on_list' => $position + 1,
                        'sect_id' => $sectId,
                        'party_affiliation' => $candidateData['party'] ?? null,
                        'withdrawn' => false,
                        'preferential_votes_count' => 0,
                    ]);
                }

                //              $this->command->info("Created {$list->name} with ".count($listData['candidates']).' candidates');
            }

            DB::commit();
            $this->command->info('Successfully seeded Chouf electoral lists and candidates!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding data: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get electoral lists data from CSV analysis
     */
    /**
     * @return array<int, array<string,mixed>>
     */
    private function getElectoralLists(): array
    {
        return [
            1 => [
                'name' => 'الشراكة والارادة',
                'color' => '#ff6a6a',
                'passed_factor' => false,
                'number' => 1,
                'candidates' => [
                    ['name' => 'تيمور وليد جنبلاط', 'sect' => 'درزي', 'party' => 'الحزب التقدمي الاشتراكي'],
                    ['name' => 'جورج جميل عدوان', 'sect' => 'ماروني', 'party' => 'القوات اللبنانية'],
                    ['name' => 'مروان محمد حماده', 'sect' => 'درزي', 'party' => 'الحزب التقدمي الاشتراكي'],
                    ['name' => 'بلال احمد عبد الله', 'sect' => 'سني', 'party' => 'الحزب التقدمي الاشتراكي'],
                    ['name' => 'سعد الدين وسيم الخطيب', 'sect' => 'سني', 'party' => null],
                    ['name' => 'حبوبه يوسف عون', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'ايلي مخايل قرداحي', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'فادي فارس المعلوف', 'sect' => 'روم كاثوليك', 'party' => null],
                ],
            ],
            2 => [
                'name' => 'صوتك ثورة',
                'color' => '#6bccf8',
                'passed_factor' => false,
                'number' => 2,
                'candidates' => [
                    ['name' => 'محمد سامي الحجار', 'sect' => 'سني', 'party' => null],
                    ['name' => 'سمير حسن عاكوم', 'sect' => 'سني', 'party' => null],
                    ['name' => 'كابي الياس القزي', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'جهاد نبيل ذبيان', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'جمال انطوان مرهج', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'ميشال خليل ابو سليمان', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'معضاد حسن ابو علي', 'sect' => 'درزي', 'party' => null],
                ],
            ],
            3 => [
                'name' => 'لائحة الجبل',
                'color' => '#8e99d3',
                'passed_factor' => false,
                'number' => 3,
                'candidates' => [
                    ['name' => 'ناجي نبيه البستاني', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'وئام ماهر نجيب وهاب', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'غسان امال عطا الله', 'sect' => 'روم كاثوليك', 'party' => null],
                    ['name' => 'فريد جورج فيليب البستاني', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'احمد حلمي نجم الدين', 'sect' => 'سني', 'party' => null],
                    ['name' => 'اسامه محمد المعوش', 'sect' => 'سني', 'party' => null],
                    ['name' => 'انطوان بشاره عبود', 'sect' => 'ماروني', 'party' => null],
                ],
            ],
            4 => [
                'name' => 'قادرين',
                'color' => '#c580d0',
                'passed_factor' => false,
                'number' => 4,
                'candidates' => [
                    ['name' => 'جوزف اسعد طعمه', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'ايمن غازي زين الدين', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'خالد ابراهيم سعد', 'sect' => 'سني', 'party' => null],
                    ['name' => 'عماد محمد الفران', 'sect' => 'سني', 'party' => null],
                ],
            ],
            5 => [
                'name' => 'توحدنا للتغيير',
                'color' => '#6ac68d',
                'passed_factor' => false,
                'number' => 5,
                'candidates' => [
                    ['name' => 'غاده غازي ماروني عيد', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'نجاة خطار عون', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'رانيه عادل غيث', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'صعود كريم ابو شبل', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'حليمه ابراهيم القعقور', 'sect' => 'سني', 'party' => null],
                    ['name' => 'عماد وفيق سيف الدين', 'sect' => 'سني', 'party' => null],
                    ['name' => 'شكري يوسف حداد', 'sect' => 'روم كاثوليك', 'party' => null],
                ],
            ],
            6 => [
                'name' => 'سيادة وطن',
                'color' => '#c27985',
                'passed_factor' => false,
                'number' => 6,
                'candidates' => [
                    ['name' => 'دعد ناصيف القزي', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'محمد عمار ابراهيم الشمعه', 'sect' => 'سني', 'party' => null],
                    ['name' => 'جويس جوزف مارون', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'مأمون احمد المأمون ملك', 'sect' => 'سني', 'party' => null],
                    ['name' => 'هشام عزات ذبيان', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'جورج ادوار سلوان', 'sect' => 'ماروني', 'party' => null],
                ],
            ],
            7 => [
                'name' => 'الجبل ينتفض',
                'color' => '#b09b94',
                'passed_factor' => false,
                'number' => 7,
                'candidates' => [
                    ['name' => 'زينه شوقي منصور', 'sect' => 'درزي', 'party' => null],
                    ['name' => 'نبيل خليل مشنتف', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'عبد الله يوسف ابو عبد الله', 'sect' => 'ماروني', 'party' => null],
                    ['name' => 'اكرم علي بريش', 'sect' => 'درزي', 'party' => null],
                ],
            ],
        ];
    }
}
