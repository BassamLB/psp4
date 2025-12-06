<?php

/**
 * Example: How to use AssignFamiliesToVoters job
 *
 * This script demonstrates various ways to dispatch the family assignment job.
 * Run with: php scripts/example_family_assignment.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\AssignFamiliesToVoters;
use Illuminate\Support\Facades\DB;

echo "AssignFamiliesToVoters Job Examples\n";
echo "====================================\n\n";

// Get current stats
$totalVoters = DB::table('voters')->count();
$votersWithFamily = DB::table('voters')->whereNotNull('family_id')->count();
$votersWithoutFamily = DB::table('voters')->whereNull('family_id')->count();
$totalFamilies = DB::table('families')->count();

echo "Current Database Stats:\n";
echo '- Total Voters: '.number_format($totalVoters)."\n";
echo '- With family_id: '.number_format($votersWithFamily)."\n";
echo '- Without family_id: '.number_format($votersWithoutFamily)."\n";
echo '- Total Families: '.number_format($totalFamilies)."\n\n";

// Example options
$examples = [
    [
        'name' => 'Full Processing',
        'description' => 'Process all voters (creates families and assigns IDs)',
        'code' => 'AssignFamiliesToVoters::dispatch();',
        'options' => [],
    ],
    [
        'name' => 'Incremental Mode',
        'description' => 'Only process voters without family_id',
        'code' => "AssignFamiliesToVoters::dispatch(['incremental' => true]);",
        'options' => ['incremental' => true],
    ],
    [
        'name' => 'Specific Voters',
        'description' => 'Process only specific voter IDs',
        'code' => "AssignFamiliesToVoters::dispatch(['voter_ids' => [1,2,3]]);",
        'options' => ['voter_ids' => [1, 2, 3]],
    ],
    [
        'name' => 'Synchronous (Testing)',
        'description' => 'Run immediately without queue (for testing)',
        'code' => 'AssignFamiliesToVoters::dispatchSync();',
        'sync' => true,
        'options' => [],
    ],
];

echo "Available Examples:\n";
echo "===================\n";
foreach ($examples as $i => $example) {
    echo ($i + 1).". {$example['name']}\n";
    echo "   Description: {$example['description']}\n";
    echo "   Code: {$example['code']}\n\n";
}

// Prompt user
echo "Enter number to run example (or 'q' to quit): ";
$input = trim(fgets(STDIN));

if ($input === 'q' || $input === '') {
    echo "Exiting.\n";
    exit(0);
}

$choice = (int) $input - 1;
if (! isset($examples[$choice])) {
    echo "Invalid choice.\n";
    exit(1);
}

$selected = $examples[$choice];
echo "\nRunning: {$selected['name']}\n";
echo str_repeat('-', 50)."\n";

$startTime = microtime(true);

if (isset($selected['sync']) && $selected['sync']) {
    // Run synchronously
    AssignFamiliesToVoters::dispatchSync($selected['options']);
    echo "\n✓ Job completed synchronously\n";
} else {
    // Dispatch to queue
    AssignFamiliesToVoters::dispatch($selected['options']);
    echo "\n✓ Job dispatched to queue: 'imports'\n";
    echo "Monitor with: php artisan queue:work redis --queue=imports\n";
}

$elapsed = microtime(true) - $startTime;

// Show updated stats
$newVotersWithFamily = DB::table('voters')->whereNotNull('family_id')->count();
$newTotalFamilies = DB::table('families')->count();
$assigned = $newVotersWithFamily - $votersWithFamily;
$familiesCreated = $newTotalFamilies - $totalFamilies;

echo "\nResults:\n";
echo '- Families created: '.number_format($familiesCreated)."\n";
echo '- Voters assigned: '.number_format($assigned)."\n";
echo '- Elapsed time: '.round($elapsed, 2)."s\n";

echo "\nNew Database Stats:\n";
echo '- Voters with family_id: '.number_format($newVotersWithFamily)."\n";
echo '- Total families: '.number_format($newTotalFamilies)."\n";

// Check for conflicts log
$conflictsDir = storage_path('app/private/imports');
if (is_dir($conflictsDir)) {
    $conflictFiles = glob($conflictsDir.'/family_sijil_conflicts_*.csv');
    if (! empty($conflictFiles)) {
        rsort($conflictFiles); // Most recent first
        $latestConflict = $conflictFiles[0];
        $conflictCount = count(file($latestConflict)) - 1; // Subtract header
        if ($conflictCount > 0) {
            echo "\n⚠ Conflicts detected: {$conflictCount}\n";
            echo "  See: {$latestConflict}\n";
        }
    }
}

echo "\n✓ Done\n";
