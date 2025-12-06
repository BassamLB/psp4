<?php

// Usage: php scripts/check_town_mismatches.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking town_id mismatches between voters and their assigned families...\n";

$query = DB::table('voters')
    ->join('families', 'voters.family_id', '=', 'families.id')
    ->whereNotNull('voters.family_id')
    ->whereRaw("COALESCE(voters.town_id,'') <> COALESCE(families.town_id,'')");

$count = (int) $query->count();
echo "Total mismatches: $count\n";

if ($count > 0) {
    $dir = __DIR__.'/../storage/app/private/imports';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/town_mismatches_sample.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, ['voter_id', 'voter_town', 'family_id', 'family_town', 'family_name', 'sijil_number', 'sect_id']);

    $rows = $query->select('voters.id as voter_id', 'voters.town_id as voter_town', 'families.id as family_id', 'families.town_id as family_town', 'voters.family_name', 'families.sijil_number', 'voters.sect_id')
        ->limit(200)
        ->get();

    foreach ($rows as $r) {
        fputcsv($fh, [(string) $r->voter_id, (string) $r->voter_town, (string) $r->family_id, (string) $r->family_town, (string) $r->family_name, (string) $r->sijil_number, (string) $r->sect_id]);
    }
    fclose($fh);
    echo "Wrote sample mismatches to: $path\n";
} else {
    echo "No town_id mismatches found.\n";
}

echo "Done.\n";
