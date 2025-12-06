<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Assigns family_id to voters based on canonical_name + town_id only.
 * Optimized for 1M+ records with incremental updates.
 *
 * Note: sijil_number is NOT used for grouping as multiple unrelated voters can share the same sijil.
 *
 * Usage:
 * - After initial import: AssignFamiliesToVoters::dispatch();
 * - For incremental updates: AssignFamiliesToVoters::dispatch(['voter_ids' => [1,2,3]]);
 * - For specific upload: AssignFamiliesToVoters::dispatch(['upload_id' => 5]);
 */
class AssignFamiliesToVoters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout for large datasets

    public $tries = 3;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(public array $options = [])
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        Log::info('AssignFamiliesToVoters: Starting', $this->options);

        // Ensure families table exists
        $this->ensureFamiliesTableExists();

        // Ensure voters.family_id column exists
        $this->ensureFamilyIdColumn();

        // Step 1: Create/update families from voters
        $this->createOrUpdateFamilies();

        // Step 2: Assign family_id to voters
        $assignedCount = $this->assignFamilyIds();

        // Step 3: Report conflicts if any
        $this->reportConflicts();

        $elapsed = microtime(true) - $startTime;
        Log::info('AssignFamiliesToVoters: Completed', [
            'assigned_count' => $assignedCount,
            'elapsed_seconds' => round($elapsed, 2),
        ]);
    }

    protected function ensureFamiliesTableExists(): void
    {
        if (Schema::hasTable('families')) {
            return;
        }

        Log::info('Creating families table');
        Schema::create('families', function ($table) {
            $table->bigIncrements('id');
            $table->string('canonical_name', 255)->index();
            $table->integer('town_id')->nullable();
            $table->string('slug', 255)->nullable();
            $table->timestamps();

            // Composite unique index for lookups (family name + town only)
            $table->unique(['canonical_name', 'town_id'], 'families_unique_key');
        });
    }

    protected function ensureFamilyIdColumn(): void
    {
        if (Schema::hasColumn('voters', 'family_id')) {
            return;
        }

        Log::info('Adding family_id column to voters table');
        Schema::table('voters', function ($table) {
            $table->unsignedBigInteger('family_id')->nullable()->after('id')->index();
        });
    }

    protected function createOrUpdateFamilies(): void
    {
        Log::info('Creating/updating families from voters using parent-child matching');

        // Get all household groups (same town_id + sijil_number)
        $households = $this->buildVotersQuery()
            ->select('town_id', 'sijil_number')
            ->whereNotNull('town_id')
            ->whereNotNull('sijil_number')
            ->distinct()
            ->get();

        Log::info('Found distinct households', ['count' => $households->count()]);

        $familyData = [];
        $unmatchedVoters = [];
        $totalProcessed = 0;

        foreach ($households as $household) {
            // Get all voters in this household
            $voters = $this->buildVotersQuery()
                ->where('town_id', $household->town_id)
                ->where('sijil_number', $household->sijil_number)
                ->get();

            $totalProcessed += $voters->count();

            // Try to identify families within this household
            $families = $this->identifyFamiliesInHousehold($voters);

            foreach ($families as $family) {
                // Prepare family record
                $canonical = $this->normalizeName($family['family_name']);
                if ($canonical === '') {
                    continue;
                }

                $key = $this->makeKey($canonical, $household->town_id, $household->sijil_number);

                if (! isset($familyData[$key])) {
                    $familyData[$key] = [
                        'canonical_name' => $canonical,
                        'town_id' => $household->town_id,
                        'sijil_number' => $household->sijil_number,
                        'father_id' => $family['father_id'] ?? null,
                        'mother_id' => $family['mother_id'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Track voters that couldn't be matched to a family
            foreach ($voters as $voter) {
                $matched = false;
                foreach ($families as $family) {
                    if (in_array($voter->id, $family['member_ids'])) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    $unmatchedVoters[] = $voter->id;
                }
            }
        }

        Log::info('Identified families', [
            'total_families' => count($familyData),
            'total_voters_processed' => $totalProcessed,
            'unmatched_voters' => count($unmatchedVoters),
        ]);

        // Bulk insert families
        if (! empty($familyData)) {
            $chunkSize = 500;
            $inserted = 0;
            foreach (array_chunk($familyData, $chunkSize) as $chunk) {
                try {
                    DB::table('families')->insertOrIgnore($chunk);
                    $inserted += count($chunk);
                } catch (\Throwable $e) {
                    Log::warning('Family insert chunk failed', [
                        'error' => $e->getMessage(),
                        'chunk_size' => count($chunk),
                    ]);
                }
            }

            Log::info('Families created/updated', ['inserted' => $inserted]);
        }
    }

    /**
     * Identify family units within a household based on parent-child relationships
     */
    protected function identifyFamiliesInHousehold($voters): array
    {
        $families = [];
        $assignedVoterIds = [];

        // Separate by gender and sort by birth date
        // gender_id=1 is male, gender_id=2 is female
        $males = $voters->where('gender_id', 1)->sortBy('date_of_birth')->values();
        $females = $voters->where('gender_id', 2)->sortBy('date_of_birth')->values();
        $others = $voters->whereNotIn('gender_id', [1, 2])->values();

        // Try to identify parent pairs and their children
        foreach ($males as $potentialFather) {
            if (in_array($potentialFather->id, $assignedVoterIds)) {
                continue;
            }

            // Look for a mother with matching full name in children's mother_full_name
            $potentialMother = null;
            foreach ($females as $female) {
                if (in_array($female->id, $assignedVoterIds)) {
                    continue;
                }

                // Check if this female is referenced as mother by any children
                $childrenReferencingThisMother = $voters->filter(function ($voter) use ($female, $potentialFather) {
                    if (empty($voter->mother_full_name) || empty($voter->father_name)) {
                        return false;
                    }

                    // Normalize names for comparison
                    $voterMotherName = $this->normalizeName($voter->mother_full_name);
                    $femaleFullName = $this->normalizeName($this->getFullName($female));
                    $voterFatherName = $this->normalizeName($voter->father_name);
                    $maleName = $this->normalizeName($potentialFather->first_name);

                    return $voterMotherName === $femaleFullName && $voterFatherName === $maleName;
                });

                if ($childrenReferencingThisMother->count() > 0) {
                    $potentialMother = $female;
                    break;
                }
            }

            // Find children matching this father (and mother if found)
            $children = $voters->filter(function ($voter) use ($potentialFather, $potentialMother, $assignedVoterIds) {
                // Skip if already assigned
                if (in_array($voter->id, $assignedVoterIds)) {
                    return false;
                }

                // Skip the parents themselves
                if ($voter->id === $potentialFather->id || ($potentialMother && $voter->id === $potentialMother->id)) {
                    return false;
                }

                // Check if father_name matches
                if (empty($voter->father_name)) {
                    return false;
                }

                $voterFatherName = $this->normalizeName($voter->father_name);
                $fatherName = $this->normalizeName($potentialFather->first_name);

                if ($voterFatherName !== $fatherName) {
                    return false;
                }

                // If we have a mother, also check mother_full_name
                if ($potentialMother && ! empty($voter->mother_full_name)) {
                    $voterMotherName = $this->normalizeName($voter->mother_full_name);
                    $motherFullName = $this->normalizeName($this->getFullName($potentialMother));

                    if ($voterMotherName !== $motherFullName) {
                        return false;
                    }
                }

                // Validate age difference (parents should be older)
                if (! empty($voter->date_of_birth) && ! empty($potentialFather->date_of_birth)) {
                    try {
                        $childBirthDate = \Carbon\Carbon::parse($voter->date_of_birth);
                        $fatherBirthDate = \Carbon\Carbon::parse($potentialFather->date_of_birth);

                        // Father should be at least 15 years older than child
                        if ($fatherBirthDate->diffInYears($childBirthDate) < 15) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        // Skip validation if dates are invalid
                    }
                }

                if ($potentialMother && ! empty($voter->date_of_birth) && ! empty($potentialMother->date_of_birth)) {
                    try {
                        $childBirthDate = \Carbon\Carbon::parse($voter->date_of_birth);
                        $motherBirthDate = \Carbon\Carbon::parse($potentialMother->date_of_birth);

                        // Mother should be at least 15 years older than child
                        if ($motherBirthDate->diffInYears($childBirthDate) < 15) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        // Skip validation if dates are invalid
                    }
                }

                return true;
            });

            // If we found at least one child (or just the parent pair), create a family
            if ($children->count() > 0 || $potentialMother) {
                $memberIds = [$potentialFather->id];
                $assignedVoterIds[] = $potentialFather->id;

                if ($potentialMother) {
                    $memberIds[] = $potentialMother->id;
                    $assignedVoterIds[] = $potentialMother->id;
                }

                foreach ($children as $child) {
                    $memberIds[] = $child->id;
                    $assignedVoterIds[] = $child->id;
                }

                $families[] = [
                    'family_name' => $potentialFather->family_name,
                    'father_id' => $potentialFather->id,
                    'mother_id' => $potentialMother ? $potentialMother->id : null,
                    'member_ids' => $memberIds,
                ];
            }
        }

        return $families;
    }

    protected function assignFamilyIds(): int
    {
        Log::info('Assigning family_id to voters based on family membership');

        $totalAssigned = 0;

        // Get all families
        $families = DB::table('families')
            ->whereNotNull('father_id')
            ->orWhereNotNull('mother_id')
            ->get();

        Log::info('Found families to assign', ['count' => $families->count()]);

        foreach ($families as $family) {
            // Get all voters in this household
            $voters = DB::table('voters')
                ->where('town_id', $family->town_id)
                ->where('sijil_number', $family->sijil_number)
                ->get();

            // Collect voter IDs for this family
            $voterIds = [];

            // Add father
            if ($family->father_id) {
                $voterIds[] = $family->father_id;
            }

            // Add mother
            if ($family->mother_id) {
                $voterIds[] = $family->mother_id;
            }

            // Get father and mother names for matching
            $father = $voters->firstWhere('id', $family->father_id);
            $mother = $voters->firstWhere('id', $family->mother_id);

            if ($father) {
                $fatherName = $this->normalizeName($father->first_name);
                $motherFullName = $mother ? $this->normalizeName($this->getFullName($mother)) : null;

                // Find children matching this father (and mother if present)
                foreach ($voters as $voter) {
                    // Skip if already added (father/mother) or no father_name
                    if (in_array($voter->id, $voterIds) || empty($voter->father_name)) {
                        continue;
                    }

                    // Check if father_name matches
                    $voterFatherName = $this->normalizeName($voter->father_name);
                    if ($voterFatherName !== $fatherName) {
                        continue;
                    }

                    // If we have a mother, also check mother_full_name
                    if ($motherFullName && ! empty($voter->mother_full_name)) {
                        $voterMotherName = $this->normalizeName($voter->mother_full_name);
                        if ($voterMotherName !== $motherFullName) {
                            continue;
                        }
                    }

                    // This voter is a child of this family
                    $voterIds[] = $voter->id;
                }
            }

            // Update all voters in this family
            if (! empty($voterIds)) {
                DB::table('voters')
                    ->whereIn('id', $voterIds)
                    ->update(['family_id' => $family->id]);

                $totalAssigned += count($voterIds);
            }
        }

        Log::info('Family assignment completed', ['total_assigned' => $totalAssigned]);

        return $totalAssigned;
    }

    protected function fetchFamiliesForKeys(array $uniqueKeys): array
    {
        if (empty($uniqueKeys)) {
            return [];
        }

        // Build optimized query to fetch all matching families at once
        $query = DB::table('families')->where(function ($q) use ($uniqueKeys) {
            foreach ($uniqueKeys as $key => $parts) {
                $q->orWhere(function ($subQ) use ($parts) {
                    $subQ->where('canonical_name', $parts['canonical'])
                        ->where('town_id', $parts['town_id'] ?? null);
                });
            }
        });

        $families = $query->get(['id', 'canonical_name', 'town_id']);

        // Map families by key
        $familyMap = [];
        foreach ($families as $family) {
            $key = $this->makeKey(
                $family->canonical_name,
                $family->town_id
            );
            $familyMap[$key] = $family->id;
        }

        return $familyMap;
    }

    protected function bulkUpdateFamilyIds(array $cases, array $ids): void
    {
        // Build CASE statement
        $casesSql = '';
        foreach ($cases as $voterId => $familyId) {
            $casesSql .= "WHEN {$voterId} THEN {$familyId} ";
        }

        $idsList = implode(',', array_map('intval', $ids));
        $sql = "UPDATE voters SET family_id = CASE id {$casesSql} END WHERE id IN ({$idsList})";

        DB::statement($sql);
    }

    protected function reportConflicts(): void
    {
        // No longer checking for sijil_number conflicts since families are grouped by name+town only
        Log::info('Conflict reporting skipped (families grouped by name+town only)');
    }

    protected function buildVotersQuery()
    {
        $query = DB::table('voters');

        // Filter by specific voter IDs if provided
        if (! empty($this->options['voter_ids'])) {
            $query->whereIn('id', $this->options['voter_ids']);
        }

        // Filter by upload batch if provided
        if (! empty($this->options['upload_id'])) {
            // You may need to add a voters.upload_id column or use a join
            // For now, this is a placeholder
            Log::info('Filtering by upload_id not yet implemented', ['upload_id' => $this->options['upload_id']]);
        }

        // Only process voters without family_id if incremental mode
        if (! empty($this->options['incremental'])) {
            $query->whereNull('family_id');
        }

        return $query;
    }

    protected function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // Remove BOM and control chars
        $name = preg_replace('/^[\x00-\x1F\x7F]+/u', '', $name);

        // Convert to lowercase
        $name = mb_strtolower($name, 'UTF-8');

        // Normalize Arabic alef variants
        $name = str_replace(['أ', 'إ', 'آ'], 'ا', $name);

        // Remove diacritics (harakat)
        $name = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $name);

        // Remove punctuation
        $name = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $name);

        // Collapse multiple spaces
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    /**
     * Get full name from voter object
     */
    protected function getFullName($voter): string
    {
        if (empty($voter->first_name)) {
            return '';
        }

        $parts = [$voter->first_name];
        if (! empty($voter->family_name)) {
            $parts[] = $voter->family_name;
        }

        return implode(' ', $parts);
    }

    protected function makeKey($canonical, $townId, $sijilNumber = null): string
    {
        return $canonical.'|'.($townId ?? 'null').'|'.($sijilNumber ?? 'null');
    }
}
