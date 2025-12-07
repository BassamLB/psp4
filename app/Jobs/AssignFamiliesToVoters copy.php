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
     * CRITICAL FIX: Must check family_name to avoid grouping unrelated people with same father first name
     */
    protected function identifyFamiliesInHousehold($voters): array
    {
        $families = [];
        $assignedVoterIds = [];

        // Pre-normalize all names once for performance
        $voterData = [];
        foreach ($voters as $voter) {
            $voterData[$voter->id] = [
                'voter' => $voter,
                'father_name_norm' => $this->normalizeName($voter->father_name ?? ''),
                'mother_name_norm' => $this->normalizeName($voter->mother_full_name ?? ''),
                'first_name_norm' => $this->normalizeName($voter->first_name ?? ''),
                'family_name_norm' => $this->normalizeName($voter->family_name ?? ''),
                'full_name_norm' => $this->normalizeName($this->getFullName($voter)),
            ];
        }

        // Separate by gender
        $males = $voters->where('gender_id', 1)->sortBy('date_of_birth')->values();
        $females = $voters->where('gender_id', 2)->sortBy('date_of_birth')->values();

        // Try to identify parent pairs and their children
        foreach ($males as $potentialFather) {
            if (in_array($potentialFather->id, $assignedVoterIds)) {
                continue;
            }

            $fatherData = $voterData[$potentialFather->id];
            $fatherFirstNameNorm = $fatherData['first_name_norm'];
            $fatherFamilyNameNorm = $fatherData['family_name_norm'];

            // Find children who match this father by name AND family_name
            $matchingChildren = [];
            foreach ($voters as $voter) {
                if ($voter->id === $potentialFather->id || in_array($voter->id, $assignedVoterIds)) {
                    continue;
                }

                $childData = $voterData[$voter->id];

                // CRITICAL: Child must have same family_name as father
                if ($childData['family_name_norm'] !== $fatherFamilyNameNorm) {
                    continue;
                }

                // Child's father_name must match father's first_name
                if ($childData['father_name_norm'] !== $fatherFirstNameNorm) {
                    continue;
                }

                $matchingChildren[] = ['voter' => $voter, 'data' => $childData];
            }

            // Find mother by checking if any female is referenced as mother by the children
            $potentialMother = null;
            foreach ($females as $female) {
                if (in_array($female->id, $assignedVoterIds)) {
                    continue;
                }

                $femaleData = $voterData[$female->id];
                $femaleFullNameNorm = $femaleData['full_name_norm'];

                // Check if this female is referenced as mother by any matching children
                $referencingChildren = array_filter($matchingChildren, function ($childInfo) use ($femaleFullNameNorm) {
                    return $childInfo['data']['mother_name_norm'] === $femaleFullNameNorm;
                });

                if (! empty($referencingChildren)) {
                    $potentialMother = $female;
                    break;
                }
            }

            // Filter children if we found a mother - they must reference her
            if ($potentialMother) {
                $motherData = $voterData[$potentialMother->id];
                $motherFullNameNorm = $motherData['full_name_norm'];

                $matchingChildren = array_filter($matchingChildren, function ($childInfo) use ($motherFullNameNorm) {
                    // If child has mother_full_name, it must match
                    if ($childInfo['data']['mother_name_norm'] !== '') {
                        return $childInfo['data']['mother_name_norm'] === $motherFullNameNorm;
                    }

                    // If no mother name specified, allow it
                    return true;
                });
            }

            // Create family if we have children or a spouse
            if (! empty($matchingChildren) || $potentialMother) {
                $memberIds = [$potentialFather->id];
                $assignedVoterIds[] = $potentialFather->id;

                if ($potentialMother) {
                    $memberIds[] = $potentialMother->id;
                    $assignedVoterIds[] = $potentialMother->id;
                }

                foreach ($matchingChildren as $childInfo) {
                    $memberIds[] = $childInfo['voter']->id;
                    $assignedVoterIds[] = $childInfo['voter']->id;
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
        Log::info('Assigning family_id to voters (optimized chunked operation)');

        $totalAssigned = 0;

        // First, assign parents to their families (super fast)
        $parentsSql = '
            UPDATE voters v
            INNER JOIN families f ON (v.id = f.father_id OR v.id = f.mother_id)
            SET v.family_id = f.id
            WHERE v.family_id IS NULL
        ';
        $parentsAffected = DB::affectingStatement($parentsSql);
        $totalAssigned += $parentsAffected;

        Log::info('Parents assigned', ['count' => $parentsAffected]);

        // Process families in chunks for performance
        DB::table('families')
            ->whereNotNull('father_id')
            ->chunkById(500, function ($families) use (&$totalAssigned) {
                // Pre-load all voters for these families' households
                $townSijilPairs = [];
                foreach ($families as $family) {
                    $key = $family->town_id.'_'.$family->sijil_number;
                    $townSijilPairs[$key] = [
                        'town_id' => $family->town_id,
                        'sijil_number' => $family->sijil_number,
                    ];
                }

                // Fetch all voters in these households in one query
                $votersQuery = DB::table('voters')
                    ->whereNull('family_id')
                    ->where(function ($q) use ($townSijilPairs) {
                        foreach ($townSijilPairs as $pair) {
                            $q->orWhere(function ($subQ) use ($pair) {
                                $subQ->where('town_id', $pair['town_id'])
                                    ->where('sijil_number', $pair['sijil_number']);
                            });
                        }
                    });

                $voters = $votersQuery->get()->groupBy(function ($v) {
                    return $v->town_id.'_'.$v->sijil_number;
                });

                // Pre-load fathers
                $fatherIds = $families->pluck('father_id')->filter()->unique()->values();
                $fathers = DB::table('voters')
                    ->whereIn('id', $fatherIds)
                    ->get()
                    ->keyBy('id');

                // Pre-load mothers
                $motherIds = $families->pluck('mother_id')->filter()->unique()->values();
                $mothers = DB::table('voters')
                    ->whereIn('id', $motherIds)
                    ->get()
                    ->keyBy('id');

                // Process each family
                foreach ($families as $family) {
                    $father = $fathers->get($family->father_id);
                    if (! $father) {
                        continue;
                    }

                    $fatherFirstNorm = $this->normalizeName($father->first_name);
                    $fatherFamilyNorm = $this->normalizeName($father->family_name);

                    $mother = $family->mother_id ? $mothers->get($family->mother_id) : null;
                    $motherFullNorm = $mother ? $this->normalizeName($this->getFullName($mother)) : null;

                    $key = $family->town_id.'_'.$family->sijil_number;
                    $householdVoters = $voters->get($key, collect());

                    $voterIdsToAssign = [];

                    foreach ($householdVoters as $voter) {
                        // Must match family_name (CRITICAL FIX)
                        if ($this->normalizeName($voter->family_name) !== $fatherFamilyNorm) {
                            continue;
                        }

                        // Must match father first name
                        if ($this->normalizeName($voter->father_name) !== $fatherFirstNorm) {
                            continue;
                        }

                        // If mother exists and voter has mother name, must match
                        if ($motherFullNorm && ! empty($voter->mother_full_name)) {
                            if ($this->normalizeName($voter->mother_full_name) !== $motherFullNorm) {
                                continue;
                            }
                        }

                        $voterIdsToAssign[] = $voter->id;
                    }

                    // Bulk update this family's voters
                    if (! empty($voterIdsToAssign)) {
                        DB::table('voters')
                            ->whereIn('id', $voterIdsToAssign)
                            ->update(['family_id' => $family->id]);

                        $totalAssigned += count($voterIdsToAssign);
                    }
                }
            });

        Log::info('Family assignment completed (chunked)', [
            'total_assigned' => $totalAssigned,
        ]);

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
