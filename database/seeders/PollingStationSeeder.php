<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\Gender;
use App\Models\PollingStation;
use App\Models\PollingStDetail;
use App\Models\Sect;
use App\Models\Town;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollingStationSeeder extends Seeder
{
    // List of duplicate town names that have district suffix
    /** @var string[] */
    private array $duplicateTowns = [
        'عميق', 'البيره', 'بكيفا', 'عين عرب', 'كوكبا',
        'الخلوات', 'علمان', 'الخريبه', 'بسابا', 'عين الرمانة',
    ];

    /** @var array<int, array<string,mixed>> */
    private array $failedRecords = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/توزيع الاقلام.csv');
        $logPath = storage_path('logs/polling_station_seeder_failed.log');

        // Clear previous log file
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");

            return;
        }

        $file = fopen($csvPath, 'r');
        if ($file === false) {
            $this->command->error("Failed to open CSV file: {$csvPath}");

            return;
        }
        $header = fgetcsv($file); // Skip header row

        // Get the first election (or create one)
        $election = Election::firstOrCreate(
            ['name' => 'الانتخابات النيابية 2026'],
            [
                'election_date' => '2026-05-15',
                'status' => 0,
            ]
        );

        // Cache for quick lookups
        $towns = Town::pluck('id', 'name');
        $sects = Sect::pluck('id', 'name');
        $genders = Gender::pluck('id', 'name');

        $pollingStations = [];
        $currentStation = null;

        DB::beginTransaction();

        try {
            $rowNumber = 1; // Start at 1 after header
            while (($row = fgetcsv($file)) !== false) {
                $rowNumber++;

                // Skip empty rows
                if (empty($row[0])) {
                    continue;
                }

                [$district, $qada, $village, $stationNumber, $location, $gender, $sect, $fromRegister, $toRegister] = $row;

                // Build town name with district suffix if it's a duplicate
                $townName = $village;
                if (in_array($village, $this->duplicateTowns)) {
                    $townName = $village.' ('.$qada.')';
                }

                // Get town ID
                if (! isset($towns[$townName])) {
                    $this->logFailedRecord($rowNumber, $row, "Town not found: {$townName}");

                    continue;
                }

                $townId = $towns[$townName];

                // Get gender ID
                if (! isset($genders[$gender])) {
                    $this->logFailedRecord($rowNumber, $row, "Gender not found: {$gender}");

                    continue;
                }

                $genderId = $genders[$gender];

                // Get sect ID
                if (! isset($sects[$sect])) {
                    $this->logFailedRecord($rowNumber, $row, "Sect not found: {$sect}");

                    continue;
                }

                $sectId = $sects[$sect];

                // Create or update polling station if we encounter a new station number
                $stationKey = "{$election->id}_{$townId}_{$stationNumber}";

                if (! isset($pollingStations[$stationKey])) {
                    $currentStation = PollingStation::create([
                        'election_id' => $election->id,
                        'town_id' => $townId,
                        'station_number' => (int) $stationNumber,
                        'location' => $location,
                        'registered_voters' => 0,
                        'white_papers_count' => 0,
                        'cancelled_papers_count' => 0,
                        'voters_count' => 0,
                        'is_open' => false,
                        'is_on_hold' => false,
                        'is_closed' => false,
                        'is_done' => false,
                        'is_checked' => false,
                        'is_final' => false,
                    ]);

                    $pollingStations[$stationKey] = $currentStation;
                    //                   $this->command->info("Created polling station #{$stationNumber} in {$village}");
                } else {
                    $currentStation = $pollingStations[$stationKey];
                }

                // Create or update polling station detail
                PollingStDetail::updateOrCreate(
                    [
                        'polling_station_id' => $currentStation->id,
                        'sect_id' => $sectId,
                        'gender_id' => $genderId,
                    ],
                    [
                        'from_sijil' => (int) $fromRegister,
                        'to_sijil' => (int) $toRegister,
                    ]
                );

                // Update registered voters count
                $votersInRange = ((int) $toRegister - (int) $fromRegister) + 1;
                $currentStation->increment('registered_voters', $votersInRange);
            }

            DB::commit();
            $this->command->info('Successfully seeded '.count($pollingStations).' polling stations');

            // Write failed records to log file
            if (! empty($this->failedRecords)) {
                $this->writeFailedRecordsLog($logPath);
                $this->command->warn('Failed to seed '.count($this->failedRecords)." records. Check log at: {$logPath}");
            } else {
                $this->command->info('All records seeded successfully!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding polling stations: '.$e->getMessage());

            // Still write failed records log
            if (! empty($this->failedRecords)) {
                $this->writeFailedRecordsLog($logPath);
            }

            throw $e;
        } finally {
            fclose($file);
        }
    }

    /**
     * Log a failed record
     */
    /**
     * @param  list<string|null>  $row
     */
    private function logFailedRecord(int $rowNumber, array $row, string $reason): void
    {
        $this->failedRecords[] = [
            'row' => $rowNumber,
            'data' => $row,
            'reason' => $reason,
        ];
    }

    /**
     * Write failed records to log file
     */
    private function writeFailedRecordsLog(string $logPath): void
    {
        $logContent = "=== Polling Station Seeder Failed Records ===\n";
        $logContent .= 'Generated at: '.now()->toDateTimeString()."\n";
        $logContent .= 'Total failed: '.count($this->failedRecords)."\n\n";

        foreach ($this->failedRecords as $failed) {
            $logContent .= 'Row #'.$failed['row']."\n";
            $logContent .= 'Reason: '.$failed['reason']."\n";
            $logContent .= 'Data: '.implode(' | ', $failed['data'])."\n";
            $logContent .= str_repeat('-', 80)."\n";
        }

        file_put_contents($logPath, $logContent);
    }
}
