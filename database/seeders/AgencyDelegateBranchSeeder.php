<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\Delegate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgencyDelegateBranchSeeder extends Seeder
{
    /** @var array<int, array<string,mixed>> */
    private array $failedRecords = [];

    /** @var array<string,int> */
    private array $createdUsers = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/وكالات الداخلية.csv');
        $logPath = storage_path('logs/agency_delegate_branch_seeder_failed.log');

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

        // Get role IDs
        $agentRoleId = Role::where('name', 'وكيل داخلية')->first()?->id;
        $delegateRoleId = Role::where('name', 'معتمد')->first()?->id;
        $branchManagerRoleId = Role::where('name', 'مدير فرع')->first()?->id;

        // Cache for tracking created records
        $agencies = [];
        $delegates = [];
        $branches = [];

        DB::beginTransaction();

        try {
            $rowNumber = 1; // Start at 1 after header
            while (($row = fgetcsv($file)) !== false) {
                $rowNumber++;

                // Skip empty rows
                if (empty($row[0]) && empty($row[3]) && empty($row[6])) {
                    continue;
                }

                // Parse CSV columns
                $agencyName = trim($row[0] ?? '');
                $agentName = trim($row[1] ?? '');
                $agentPhone = trim($row[2] ?? '');
                $delegateName = trim($row[3] ?? '');
                $delegateResponsible = trim($row[4] ?? '');
                $delegatePhone = trim($row[5] ?? '');
                $branchName = trim($row[6] ?? '');
                $branchManager = trim($row[7] ?? '');
                $branchPhone = trim($row[8] ?? '');

                // Skip if all required fields are empty
                if (empty($agencyName) && empty($delegateName) && empty($branchName)) {
                    continue;
                }

                // Create or get Agency
                $agencyId = null;
                $agentUserId = null;
                if (! empty($agencyName)) {
                    if (! isset($agencies[$agencyName])) {
                        // Create agent user if name and phone exist
                        if (! empty($agentName) && ! empty($agentPhone)) {
                            $agentUserId = $this->createOrGetUser($agentName, $agentPhone, $agentRoleId);
                        }

                        $agency = Agency::firstOrCreate(
                            ['name' => $agencyName],
                            ['responsible_id' => $agentUserId]
                        );
                        $agencies[$agencyName] = [
                            'id' => $agency->id,
                            'user_id' => $agentUserId,
                        ];
                    }
                    $agencyId = $agencies[$agencyName]['id'];
                }

                // Create or get Delegate
                $delegateId = null;
                if (! empty($delegateName) && $agencyId) {
                    $delegateKey = "{$agencyId}_{$delegateName}";
                    if (! isset($delegates[$delegateKey])) {
                        try {
                            // Create delegate user if name and phone exist
                            $delegateUserId = null;
                            if (! empty($delegateResponsible) && ! empty($delegatePhone)) {
                                $delegateUserId = $this->createOrGetUser($delegateResponsible, $delegatePhone, $delegateRoleId);
                            }

                            $delegate = Delegate::firstOrCreate(
                                [
                                    'agency_id' => $agencyId,
                                    'name' => $delegateName,
                                ],
                                ['responsible_id' => $delegateUserId]
                            );
                            $delegates[$delegateKey] = $delegate->id;
                        } catch (\Exception $e) {
                            $this->logFailedRecord($rowNumber, $row, "Failed to create delegate '{$delegateName}': ".$e->getMessage());

                            continue;
                        }
                    }
                    $delegateId = $delegates[$delegateKey];
                } elseif (! empty($delegateName) && ! $agencyId) {
                    $this->logFailedRecord($rowNumber, $row, "Delegate '{$delegateName}' has no agency");

                    continue;
                }

                // Create or get Branch
                if (! empty($branchName) && $delegateId) {
                    $branchKey = "{$delegateId}_{$branchName}";
                    if (! isset($branches[$branchKey])) {
                        try {
                            // Create branch manager user if name and phone exist
                            $branchManagerUserId = null;
                            if (! empty($branchManager) && ! empty($branchPhone)) {
                                $branchManagerUserId = $this->createOrGetUser($branchManager, $branchPhone, $branchManagerRoleId);
                            }

                            $branch = Branch::firstOrCreate(
                                [
                                    'delegate_id' => $delegateId,
                                    'name' => $branchName,
                                ],
                                ['responsible_id' => $branchManagerUserId]
                            );
                            $branches[$branchKey] = $branch->id;
                        } catch (\Exception $e) {
                            $this->logFailedRecord($rowNumber, $row, "Failed to create branch '{$branchName}': ".$e->getMessage());

                            continue;
                        }
                    }
                } elseif (! empty($branchName) && ! $delegateId) {
                    $this->logFailedRecord($rowNumber, $row, "Branch '{$branchName}' has no delegate");

                    continue;
                }
            }

            DB::commit();
            $this->command->info('Successfully seeded '.count($agencies).' agencies, '.
                                count($delegates).' delegates, '.
                                count($branches).' branches, and '.
                                count($this->createdUsers).' users');

            // Write failed records to log file
            if (! empty($this->failedRecords)) {
                $this->writeFailedRecordsLog($logPath);
                $this->command->warn('Failed to seed '.count($this->failedRecords)." records. Check log at: {$logPath}");
            } else {
                $this->command->info('All records seeded successfully!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding agencies/delegates/branches: '.$e->getMessage());

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
     * Create or get user
     */
    private function createOrGetUser(string $name, string $phone, ?int $roleId): ?int
    {
        if (empty($name) || empty($phone)) {
            return null;
        }

        // Check if already created in this session
        $userKey = $phone;
        if (isset($this->createdUsers[$userKey])) {
            return $this->createdUsers[$userKey];
        }

        try {
            // Generate email from phone
            $email = $phone.'@psp.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'role_id' => $roleId,
                ]
            );

            $this->createdUsers[$userKey] = $user->id;

            return $user->id;
        } catch (\Exception $e) {
            $this->command->warn("Failed to create user {$name} ({$phone}): ".$e->getMessage());

            return null;
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
        $logContent = "=== Agency/Delegate/Branch Seeder Failed Records ===\n";
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
