<?php

// Usage: php scripts/inspect_families.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Top 20 family_name counts:\n";
$top = DB::table('voters')
    ->select('family_name', DB::raw('count(*) as cnt'))
    ->whereNotNull('family_name')
    ->where('family_name', '!=', '')
    ->groupBy('family_name')
    ->orderByDesc('cnt')
    ->limit(20)
    ->get();

foreach ($top as $row) {
    echo sprintf("%4d  %s\n", $row->cnt, $row->family_name);
}

echo "\nSamples for top families:\n";
foreach ($top as $row) {
    echo "\n== ".$row->family_name.' ('.$row->cnt.") ==\n";
    // choose columns that exist on the voters table
    $desired = ['id', 'first_name', 'family_name', 'father_name', 'mother_full_name', 'date_of_birth', 'town', 'sijil_number'];
    $available = Schema::getColumnListing('voters');
    $cols = array_values(array_intersect($desired, $available));
    if (empty($cols)) {
        echo "No usable columns found on voters table.\n";
        exit(1);
    }
    $members = DB::table('voters')
        ->select($cols)
        ->where('family_name', $row->family_name)
        ->orderBy('date_of_birth')
        ->limit(10)
        ->get();
    foreach ($members as $m) {
        $parts = [];
        foreach ($cols as $c) {
            $parts[] = ($m->$c ?? '');
        }
        echo implode(' | ', $parts)."\n";
    }
}

echo "\nDone.\n";
