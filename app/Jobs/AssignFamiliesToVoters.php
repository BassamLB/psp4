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
use Illuminate\Support\Collection;

/**
 * Assigns family_id to voters based on canonical_name + town_id only.
 * Optimized for 1M+ records with incremental updates.
 *
 * Usage:
 * - After initial import: AssignFamiliesToVoters::dispatch();
 * - For incremental updates: AssignFamiliesToVoters::dispatch(['voter_ids' => [1,2,3]]);
 * - For specific upload: AssignFamiliesToVoters::dispatch(['upload_id' => 5]);
 */
class AssignFamiliesToVoters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    // Cache for normalized names to avoid repeated processing
    private array $nameCache = [];
    
    // Batch size constants
    private const FAMILY_CHUNK_SIZE = 500;
    private const VOTER_CHUNK_SIZE = 1000;

    public function __construct(public array $options = [])
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        Log::info('AssignFamiliesToVoters: Starting', $this->options);

        $this->ensureDatabaseStructure();

        // Step 1: Create/update families from voters
        $this->createOrUpdateFamilies();

        // Step 2: Assign family_id to voters
        $assignedCount = $this->assignFamilyIds();

        $elapsed = microtime(true) - $startTime;
        Log::info('AssignFamiliesToVoters: Completed', [
            'assigned_count' => $assignedCount,
            'elapsed_seconds' => round($elapsed, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }

    protected function ensureDatabaseStructure(): void
    {
        if (!Schema::hasTable('families')) {
            Log::info('Creating families table');
            Schema::create('families', function ($table) {
                $table->bigIncrements('id');
                $table->string('canonical_name', 255);
                $table->integer('town_id')->nullable();
                $table->string('sijil_number', 100)->nullable();
                $table->unsignedBigInteger('father_id')->nullable();
                $table->unsignedBigInteger('mother_id')->nullable();
                $table->string('slug', 255)->nullable();
                $table->timestamps();

                $table->unique(['canonical_name', 'town_id', 'sijil_number'], 'families_unique_key');
                $table->index(['town_id', 'sijil_number'], 'families_household_idx');
            });
        }

        if (!Schema::hasColumn('voters', 'family_id')) {
            Log::info('Adding family_id column to voters table');
            Schema::table('voters', function ($table) {
                $table->unsignedBigInteger('family_id')->nullable()->after('id');
                $table->index('family_id');
            });
        }

        // Ensure critical indexes exist
        if (!Schema::hasColumn('voters', 'town_id') || 
            !$this->hasIndex('voters', 'voters_town_sijil_idx')) {
            Schema::table('voters', function ($table) {
                $table->index(['town_id', 'sijil_number'], 'voters_town_sijil_idx');
            });
        }
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }

    protected function createOrUpdateFamilies(): void
    {
        Log::info('Creating/updating families from voters');

        $totalProcessed = 0;
        $totalFamilies = 0;

        // Process households in chunks to manage memory
        $this->buildVotersQuery()
            ->select('town_id', 'sijil_number')
            ->whereNotNull('town_id')
            ->whereNotNull('sijil_number')
            ->distinct()
            ->orderBy('town_id')
            ->orderBy('sijil_number')
            ->chunk(100, function ($households) use (&$totalProcessed, &$totalFamilies) {
                $familyData = $this->processBatchOfHouseholds($households, $totalProcessed);
                
                if (!empty($familyData)) {
                    $this->bulkInsertFamilies($familyData);
                    $totalFamilies += count($familyData);
                }
            });

        Log::info('Families created/updated', [
            'total_families' => $totalFamilies,
            'total_voters_processed' => $totalProcessed,
        ]);
    }

    protected function processBatchOfHouseholds(Collection $households, int &$totalProcessed): array
    {
        // Fetch all voters for these households in one query
        $votersByHousehold = $this->fetchVotersForHouseholds($households);
        
        $familyData = [];

        foreach ($households as $household) {
            $key = "{$household->town_id}_{$household->sijil_number}";
            $voters = $votersByHousehold->get($key, collect());
            
            if ($voters->isEmpty()) {
                continue;
            }

            $totalProcessed += $voters->count();

            // Identify families within this household
            $families = $this->identifyFamiliesInHousehold($voters);

            foreach ($families as $family) {
                $canonical = $this->normalizeName($family['family_name']);
                if ($canonical === '') {
                    continue;
                }

                $familyKey = $this->makeKey($canonical, $household->town_id, $household->sijil_number);

                if (!isset($familyData[$familyKey])) {
                    $familyData[$familyKey] = [
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
        }

        return $familyData;
    }

    protected function fetchVotersForHouseholds(Collection $households): Collection
    {
        $conditions = [];
        $bindings = [];

        foreach ($households as $household) {
            $conditions[] = '(town_id = ? AND sijil_number = ?)';
            $bindings[] = $household->town_id;
            $bindings[] = $household->sijil_number;
        }

        $whereSql = implode(' OR ', $conditions);

        return DB::table('voters')
            ->whereRaw("({$whereSql})", $bindings)
            ->get()
            ->groupBy(fn($v) => "{$v->town_id}_{$v->sijil_number}");
    }

    protected function identifyFamiliesInHousehold(Collection $voters): array
    {
        $families = [];
        $assignedVoterIds = [];

        // Pre-normalize all names once
        $voterData = $this->preprocessVoterData($voters);

        // Separate by gender and sort by age
        $males = $voters->where('gender_id', 1)->sortBy('date_of_birth')->values();
        $females = $voters->where('gender_id', 2)->sortBy('date_of_birth')->values();

        // Try to identify parent pairs and their children
        foreach ($males as $potentialFather) {
            if (in_array($potentialFather->id, $assignedVoterIds)) {
                continue;
            }

            $family = $this->buildFamily($potentialFather, $females, $voters, $voterData, $assignedVoterIds);
            
            if ($family) {
                $families[] = $family;
            }
        }

        return $families;
    }

    protected function preprocessVoterData(Collection $voters): array
    {
        $data = [];
        foreach ($voters as $voter) {
            $data[$voter->id] = [
                'voter' => $voter,
                'father_name_norm' => $this->normalizeName($voter->father_name ?? ''),
                'mother_name_norm' => $this->normalizeName($voter->mother_full_name ?? ''),
                'first_name_norm' => $this->normalizeName($voter->first_name ?? ''),
                'family_name_norm' => $this->normalizeName($voter->family_name ?? ''),
                'full_name_norm' => $this->normalizeName($this->getFullName($voter)),
            ];
        }
        return $data;
    }

    protected function buildFamily($potentialFather, Collection $females, Collection $voters, array $voterData, array &$assignedVoterIds): ?array
    {
        $fatherData = $voterData[$potentialFather->id];
        $fatherFirstNameNorm = $fatherData['first_name_norm'];
        $fatherFamilyNameNorm = $fatherData['family_name_norm'];

        // Find matching children
        $matchingChildren = $this->findMatchingChildren($voters, $voterData, $fatherFirstNameNorm, $fatherFamilyNameNorm, $assignedVoterIds);

        if (empty($matchingChildren)) {
            return null;
        }

        // Find mother
        $potentialMother = $this->findMother($females, $voterData, $matchingChildren, $assignedVoterIds);

        // Filter children by mother if found
        if ($potentialMother) {
            $motherFullNameNorm = $voterData[$potentialMother->id]['full_name_norm'];
            $matchingChildren = $this->filterChildrenByMother($matchingChildren, $motherFullNameNorm);
        }

        // Build family structure
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

        return [
            'family_name' => $potentialFather->family_name,
            'father_id' => $potentialFather->id,
            'mother_id' => $potentialMother?->id,
            'member_ids' => $memberIds,
        ];
    }

    protected function findMatchingChildren(Collection $voters, array $voterData, string $fatherFirstNameNorm, string $fatherFamilyNameNorm, array $assignedVoterIds): array
    {
        $matchingChildren = [];

        foreach ($voters as $voter) {
            if (in_array($voter->id, $assignedVoterIds)) {
                continue;
            }

            $childData = $voterData[$voter->id];

            // Must match family_name and father's first_name
            if ($childData['family_name_norm'] === $fatherFamilyNameNorm &&
                $childData['father_name_norm'] === $fatherFirstNameNorm) {
                $matchingChildren[] = ['voter' => $voter, 'data' => $childData];
            }
        }

        return $matchingChildren;
    }

    protected function findMother(Collection $females, array $voterData, array $matchingChildren, array $assignedVoterIds): mixed
    {
        foreach ($females as $female) {
            if (in_array($female->id, $assignedVoterIds)) {
                continue;
            }

            $femaleFullNameNorm = $voterData[$female->id]['full_name_norm'];

            // Check if any child references this female as mother
            $referencingChildren = array_filter($matchingChildren, 
                fn($childInfo) => $childInfo['data']['mother_name_norm'] === $femaleFullNameNorm
            );

            if (!empty($referencingChildren)) {
                return $female;
            }
        }

        return null;
    }

    protected function filterChildrenByMother(array $matchingChildren, string $motherFullNameNorm): array
    {
        return array_filter($matchingChildren, function ($childInfo) use ($motherFullNameNorm) {
            $childMotherNorm = $childInfo['data']['mother_name_norm'];
            return $childMotherNorm === '' || $childMotherNorm === $motherFullNameNorm;
        });
    }

    protected function bulkInsertFamilies(array $familyData): void
    {
        foreach (array_chunk($familyData, self::FAMILY_CHUNK_SIZE) as $chunk) {
            try {
                DB::table('families')->insertOrIgnore($chunk);
            } catch (\Throwable $e) {
                Log::warning('Family insert chunk failed', [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk),
                ]);
            }
        }
    }

    protected function assignFamilyIds(): int
    {
        Log::info('Assigning family_id to voters');

        // First, assign parents directly (very fast single query)
        $parentsAssigned = $this->assignParentsToFamilies();
        
        // Then assign children in optimized chunks
        $childrenAssigned = $this->assignChildrenToFamilies();

        $totalAssigned = $parentsAssigned + $childrenAssigned;

        Log::info('Family assignment completed', [
            'parents_assigned' => $parentsAssigned,
            'children_assigned' => $childrenAssigned,
            'total_assigned' => $totalAssigned,
        ]);

        return $totalAssigned;
    }

    protected function assignParentsToFamilies(): int
    {
        $sql = '
            UPDATE voters v
            INNER JOIN families f ON (v.id = f.father_id OR v.id = f.mother_id)
            SET v.family_id = f.id
            WHERE v.family_id IS NULL
        ';
        
        return DB::affectingStatement($sql);
    }

    protected function assignChildrenToFamilies(): int
    {
        $totalAssigned = 0;

        DB::table('families')
            ->whereNotNull('father_id')
            ->orderBy('id')
            ->chunk(self::FAMILY_CHUNK_SIZE, function ($families) use (&$totalAssigned) {
                $assigned = $this->processChunkOfFamilies($families);
                $totalAssigned += $assigned;
            });

        return $totalAssigned;
    }

    protected function processChunkOfFamilies(Collection $families): int
    {
        // Pre-load all relevant data
        $households = $this->extractHouseholdsFromFamilies($families);
        $voters = $this->fetchVotersForHouseholds($households);
        $fathers = $this->fetchParents($families->pluck('father_id')->filter());
        $mothers = $this->fetchParents($families->pluck('mother_id')->filter());

        $totalAssigned = 0;

        foreach ($families as $family) {
            $father = $fathers->get($family->father_id);
            if (!$father) {
                continue;
            }

            $assigned = $this->assignVotersToFamily($family, $father, $mothers->get($family->mother_id), $voters);
            $totalAssigned += $assigned;
        }

        return $totalAssigned;
    }

    protected function extractHouseholdsFromFamilies(Collection $families): Collection
    {
        return $families->map(fn($f) => (object)[
            'town_id' => $f->town_id,
            'sijil_number' => $f->sijil_number,
        ])->unique(fn($h) => "{$h->town_id}_{$h->sijil_number}");
    }

    protected function fetchParents(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('voters')
            ->whereIn('id', $ids->unique())
            ->get()
            ->keyBy('id');
    }

    protected function assignVotersToFamily($family, $father, $mother, Collection $votersByHousehold): int
    {
        $fatherFirstNorm = $this->normalizeName($father->first_name);
        $fatherFamilyNorm = $this->normalizeName($father->family_name);
        $motherFullNorm = $mother ? $this->normalizeName($this->getFullName($mother)) : null;

        $key = "{$family->town_id}_{$family->sijil_number}";
        $householdVoters = $votersByHousehold->get($key, collect())->whereNull('family_id');

        $voterIdsToAssign = [];

        foreach ($householdVoters as $voter) {
            if ($this->isVoterInFamily($voter, $fatherFirstNorm, $fatherFamilyNorm, $motherFullNorm)) {
                $voterIdsToAssign[] = $voter->id;
            }
        }

        if (!empty($voterIdsToAssign)) {
            DB::table('voters')
                ->whereIn('id', $voterIdsToAssign)
                ->update(['family_id' => $family->id]);
        }

        return count($voterIdsToAssign);
    }

    protected function isVoterInFamily($voter, string $fatherFirstNorm, string $fatherFamilyNorm, ?string $motherFullNorm): bool
    {
        // Must match family_name
        if ($this->normalizeName($voter->family_name) !== $fatherFamilyNorm) {
            return false;
        }

        // Must match father first name
        if ($this->normalizeName($voter->father_name) !== $fatherFirstNorm) {
            return false;
        }

        // If mother exists and voter has mother name, must match
        if ($motherFullNorm && !empty($voter->mother_full_name)) {
            if ($this->normalizeName($voter->mother_full_name) !== $motherFullNorm) {
                return false;
            }
        }

        return true;
    }

    protected function buildVotersQuery()
    {
        $query = DB::table('voters');

        if (!empty($this->options['voter_ids'])) {
            $query->whereIn('id', $this->options['voter_ids']);
        }

        if (!empty($this->options['upload_id'])) {
            Log::info('Filtering by upload_id not yet implemented', ['upload_id' => $this->options['upload_id']]);
        }

        if (!empty($this->options['incremental'])) {
            $query->whereNull('family_id');
        }

        return $query;
    }

    protected function normalizeName(string $name): string
    {
        // Use cache to avoid repeated normalization
        if (isset($this->nameCache[$name])) {
            return $this->nameCache[$name];
        }

        $normalized = $this->performNormalization($name);
        
        // Limit cache size to prevent memory issues
        if (count($this->nameCache) < 10000) {
            $this->nameCache[$name] = $normalized;
        }

        return $normalized;
    }

    protected function performNormalization(string $name): string
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

        // Remove diacritics
        $name = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $name);

        // Remove punctuation
        $name = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $name);

        // Collapse multiple spaces
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    protected function getFullName($voter): string
    {
        if (empty($voter->first_name)) {
            return '';
        }

        return trim(($voter->first_name ?? '') . ' ' . ($voter->family_name ?? ''));
    }

    protected function makeKey(string $canonical, ?int $townId, ?string $sijilNumber = null): string
    {
        return $canonical . '|' . ($townId ?? 'null') . '|' . ($sijilNumber ?? 'null');
    }
}
