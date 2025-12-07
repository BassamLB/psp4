<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Starting family assignment test...\n";

$start = microtime(true);

try {
    App\Jobs\AssignFamiliesToVoters::dispatchSync([]);
    $elapsed = microtime(true) - $start;
    
    echo "\n✅ Completed in " . round($elapsed, 2) . " seconds\n\n";
    
    // Check results for family #17
    $families = DB::table('families')
        ->where('sijil_number', 17)
        ->where('town_id', 271)
        ->get();
    
    echo "Families created for sijil #17, town 271:\n";
    foreach ($families as $family) {
        $count = DB::table('voters')->where('family_id', $family->id)->count();
        echo "  - {$family->canonical_name} (ID: {$family->id}): {$count} members\n";
    }
    
} catch (\Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
