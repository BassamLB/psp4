<?php

// Usage: php scripts/create_assign_families.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function normalizeName(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    // remove BOM and control chars
    $s = preg_replace('/^[\x00-\x1F\x7F]+/u', '', $s);
    // lower
    $s = mb_strtolower($s, 'UTF-8');
    // normalize Arabic alef variants to bare alef
    $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
    // remove diacritics (harakat)
    $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $s);
    // remove punctuation
    $s = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s);
    // collapse spaces
    $s = preg_replace('/\s+/u', ' ', $s);

    return trim($s);
}

echo "Creating `families` table if it doesn't exist...\n";
if (! Schema::hasTable('families')) {
    Schema::create('families', function ($table) {
        $table->bigIncrements('id');
        $table->string('canonical_name', 255)->index();
        $table->integer('town_id')->nullable();
        $table->integer('sect_id')->nullable();
        $table->string('slug', 255)->nullable();
        $table->timestamps();
    });
    echo "Created families table.\n";
} else {
    echo "families table exists.\n";
}

// Gather distinct family_name + town_id + sect_id
echo "Gathering distinct family name combinations...\n";
// include `sijil_number` in the distinct set so we only group people who share the same sijil
$distinct = DB::table('voters')
    ->select('family_name', 'town_id', 'sect_id', 'sijil_number')
    ->whereNotNull('family_name')
    ->where('family_name', '!=', '')
    ->distinct()
    ->get();

echo 'Found distinct family_name entries: '.$distinct->count()."\n";

$map = [];
foreach ($distinct as $r) {
    $canonical = normalizeName((string) $r->family_name);
    if ($canonical === '') {
        continue;
    }
    // use sijil_number as part of the family key to ensure members share the same registry id
    $sijil = isset($r->sijil_number) ? trim((string) $r->sijil_number) : '';
    $key = $canonical.'|'.($r->town_id ?? '').'|'.($r->sect_id ?? '').'|'.$sijil;
    if (! isset($map[$key])) {
        $map[$key] = ['canonical' => $canonical, 'town_id' => $r->town_id, 'sect_id' => $r->sect_id, 'sijil' => $sijil, 'count' => 0];
    }
    $map[$key]['count']++;
}

echo 'Unique canonical family combinations: '.count($map)."\n";

// Insert families (skip existing canonical+town+sect)
echo "Inserting families...\n";
$inserted = 0;
foreach ($map as $key => $info) {
    $exists = DB::table('families')
        ->where('canonical_name', $info['canonical'])
        ->where('town_id', $info['town_id'])
        ->where('sect_id', $info['sect_id'])
        ->where(function ($q) use ($info) {
            if (! empty($info['sijil'] ?? '')) {
                $q->where('sijil_number', $info['sijil']);
            } else {
                $q->whereNull('sijil_number')->orWhere('sijil_number', '');
            }
        })
        ->exists();
    if (! $exists) {
        $id = DB::table('families')->insertGetId([
            'canonical_name' => $info['canonical'],
            'sijil_number' => $info['sijil'] ?? null,
            'town_id' => $info['town_id'],
            'sect_id' => $info['sect_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inserted++;
    }
}
echo "Inserted $inserted new families.\n";

// Assign family_id to voters in chunks (avoid loading all families into memory)
echo "Assigning family_id to voters in chunks...\n";
$updatedTotal = 0;
$chunkSize = 1000;
DB::table('voters')->orderBy('id')->chunk($chunkSize, function ($rows) use (&$updatedTotal) {
    $rowKeyMap = [];
    $uniqueKeys = [];
    foreach ($rows as $row) {
        $fam = normalizeName((string) $row->family_name);
        if ($fam === '') {
            continue;
        }
        $sij = isset($row->sijil_number) ? trim((string) $row->sijil_number) : '';
        $k = $fam.'|'.($row->town_id ?? '').'|'.($row->sect_id ?? '').'|'.$sij;
        $rowKeyMap[$row->id] = $k;
        if (! isset($uniqueKeys[$k])) {
            $uniqueKeys[$k] = ['canonical' => $fam, 'town_id' => $row->town_id, 'sect_id' => $row->sect_id, 'sijil' => $sij];
        }
    }

    if (empty($rowKeyMap)) {
        return;
    }

    // Query families that match any of the unique keys for this chunk
    $query = DB::table('families');
    $first = true;
    foreach ($uniqueKeys as $k => $parts) {
        if ($first) {
            $query->where(function ($q) use ($parts) {
                $q->where('canonical_name', $parts['canonical'])
                    ->where('town_id', $parts['town_id'])
                    ->where('sect_id', $parts['sect_id']);
                if ($parts['sijil'] !== '') {
                    $q->where('sijil_number', $parts['sijil']);
                } else {
                    $q->whereNull('sijil_number')->orWhere('sijil_number', '');
                }
            });
            $first = false;
        } else {
            $query->orWhere(function ($q) use ($parts) {
                $q->where('canonical_name', $parts['canonical'])
                    ->where('town_id', $parts['town_id'])
                    ->where('sect_id', $parts['sect_id']);
                if ($parts['sijil'] !== '') {
                    $q->where('sijil_number', $parts['sijil']);
                } else {
                    $q->whereNull('sijil_number')->orWhere('sijil_number', '');
                }
            });
        }
    }
    $found = $query->get(['id', 'canonical_name', 'town_id', 'sect_id', 'sijil_number']);
    $familyMapLocal = [];
    foreach ($found as $f) {
        $k = $f->canonical_name.'|'.($f->town_id ?? '').'|'.($f->sect_id ?? '').'|'.(($f->sijil_number ?? '') ?: '');
        $familyMapLocal[$k] = $f->id;
    }

    $cases = [];
    $ids = [];
    foreach ($rowKeyMap as $rowId => $k) {
        if (isset($familyMapLocal[$k])) {
            $cases[$rowId] = $familyMapLocal[$k];
            $ids[] = $rowId;
        }
    }

    if (! empty($ids)) {
        // build CASE update
        $casesSql = '';
        foreach ($cases as $id => $fid) {
            $casesSql .= "WHEN $id THEN $fid ";
        }
        $idsList = implode(',', $ids);
        $sql = "UPDATE voters SET family_id = CASE id $casesSql END WHERE id IN ($idsList)";
        DB::statement($sql);
        $updatedTotal += count($ids);
    }
});

echo "Assigned family_id to $updatedTotal voters.\n";

// Add column if missing (safe)
if (! Schema::hasColumn('voters', 'family_id')) {
    Schema::table('voters', function ($table) {
        $table->unsignedBigInteger('family_id')->nullable()->after('id');
    });
}

// Create index if it doesn't already exist — wrap in try/catch to tolerate duplicates
try {
    Schema::table('voters', function ($table) {
        $table->index('family_id');
    });
} catch (\Throwable $e) {
    // Ignore duplicate index errors or other non-fatal issues
    echo 'Note: index creation skipped or failed: '.$e->getMessage()."\n";
}

echo "Done. You can now query voters ordered by family_id or join families table.\n";

// Report: find canonical groups (canonical|town|sect) that have multiple distinct sijil_numbers
echo "Scanning for conflicts (same canonical + town + sect but multiple sijil_numbers)...\n";
$conflicts = DB::table('families')
    ->select('canonical_name', 'town_id', 'sect_id', DB::raw('COUNT(DISTINCT sijil_number) as sijil_count'))
    ->groupBy('canonical_name', 'town_id', 'sect_id')
    ->having('sijil_count', '>', 1)
    ->get();
if ($conflicts->isEmpty()) {
    echo "No canonical conflicts found.\n";
} else {
    $dir = __DIR__.'/../storage/app/private/imports';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/family_sijil_conflicts.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, ['canonical_name', 'town_id', 'sect_id', 'sijil_count']);
    foreach ($conflicts as $c) {
        fputcsv($fh, [(string) $c->canonical_name, (string) $c->town_id, (string) $c->sect_id, (string) $c->sijil_count]);
    }
    fclose($fh);
    echo "Wrote conflicts to: $path\n";
}
