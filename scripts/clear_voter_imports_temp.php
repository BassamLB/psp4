<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $before = DB::table('voter_imports_temp')->count();
    echo "Before: voter_imports_temp rows = {$before}\n";

    // Truncate table (use statement to support different DBs)
    DB::statement('TRUNCATE TABLE voter_imports_temp');

    $after = DB::table('voter_imports_temp')->count();
    echo "After: voter_imports_temp rows = {$after}\n";
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}

echo "Done.\n";
