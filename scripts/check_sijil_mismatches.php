<?php

// Usage: php scripts/check_sijil_mismatches.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking sijil_number mismatches between voters and their assigned families...\n";

$query = DB::table('voters')
    ->join('families', 'voters.family_id', '=', 'families.id')
    ->whereNotNull('voters.family_id')
    ->whereRaw("COALESCE(voters.sijil_number,'') <> COALESCE(families.sijil_number,'')");

$count = (int) $query->count();
echo "Total mismatches: $count\n";

if ($count > 0) {
    $dir = __DIR__.'/../storage/app/private/imports';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/sijil_mismatches_sample.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, ['voter_id', 'voter_sijil', 'family_id', 'family_sijil', 'family_name', 'town_id', 'sect_id']);

    $rows = $query->select('voters.id as voter_id', 'voters.sijil_number as voter_sijil', 'families.id as family_id', 'families.sijil_number as family_sijil', 'voters.family_name', 'voters.town_id', 'voters.sect_id')
        ->limit(200)
        ->get();

    foreach ($rows as $r) {
        fputcsv($fh, [(string) $r->voter_id, (string) $r->voter_sijil, (string) $r->family_id, (string) $r->family_sijil, (string) $r->family_name, (string) $r->town_id, (string) $r->sect_id]);
    }
    fclose($fh);
    echo "Wrote sample mismatches to: $path\n";
} else {
    echo "No mismatches found.\n";
}

// Also report how many voters have a non-empty sijil_number (quick health check)
$totalWithSijil = (int) DB::table('voters')->whereNotNull('sijil_number')->where('sijil_number', '!=', '')->count();
$totalVoters = (int) DB::table('voters')->count();
echo "Voters with non-empty sijil_number: $totalWithSijil / $totalVoters\n";

echo "Done.\n";
