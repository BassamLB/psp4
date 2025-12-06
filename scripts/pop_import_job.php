<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $redis = app('redis');
    $item = $redis->lpop('queues:imports');
    if (! $item) {
        echo "No item popped from queues:imports\n";
        exit(0);
    }

    echo "Popped raw item:\n";
    echo $item.PHP_EOL.PHP_EOL;

    // Save a copy for inspection
    file_put_contents(__DIR__.'/popped_import_item.json', $item);

    // Try to decode JSON if possible
    $decoded = json_decode($item, true);
    if ($decoded) {
        if (isset($decoded['displayName'])) {
            echo 'displayName: '.$decoded['displayName'].PHP_EOL;
        }
        if (isset($decoded['commandName'])) {
            echo 'commandName: '.$decoded['commandName'].PHP_EOL;
        }
        if (isset($decoded['command'])) {
            echo "(command key present; may contain serialized payload)\n";
        }
    } else {
        echo "Item is not JSON (may be serialized). See 'scripts/popped_import_item.json' for raw content.\n";
    }
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}

echo "Done.\n";
