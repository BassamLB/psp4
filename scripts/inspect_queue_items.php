<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$redis = app('redis');

$len = $redis->llen('queues:imports');
echo "queues:imports length = $len\n\n";

for ($i = 0; $i < $len; $i++) {
    echo "=== item #$i ===\n";
    $payload = $redis->lindex('queues:imports', $i);
    $obj = @json_decode($payload, true);
    if (! is_array($obj)) {
        echo "payload not JSON\n";

        continue;
    }
    $display = $obj['displayName'] ?? 'N/A';
    echo "displayName: $display\n";
    $command = $obj['data']['command'] ?? null;
    if (! $command) {
        echo "no command found\n";

        continue;
    }

    // The command is a serialized PHP object string
    try {
        $job = unserialize($command);
    } catch (Throwable $e) {
        echo 'unserialize failed: '.$e->getMessage()."\n";

        continue;
    }

    $class = is_object($job) ? get_class($job) : gettype($job);
    echo "job class: $class\n";

    // Try to inspect known properties
    if (is_object($job)) {
        $vars = (array) $job;
        // Normalize property names (strip null bytes for protected/private)
        $clean = [];
        foreach ($vars as $k => $v) {
            $k2 = preg_replace('/^[^\$]*\$/', '', $k);
            // human-friendly key
            $k2 = preg_replace('/^\x00.*\x00/', '', $k2);
            $clean[$k2] = $v;
        }

        foreach ($clean as $k => $v) {
            echo "- $k: ";
            if (is_object($v)) {
                echo get_class($v);
                // If it's a ModelIdentifier, print class & id
                if (is_a($v, 'Illuminate\\Contracts\\Database\\ModelIdentifier')) {
                    echo ' (model class='.($v->class ?? 'n/a').', id='.($v->id ?? 'n/a').')';
                }
                echo "\n";
            } elseif (is_array($v)) {
                echo json_encode($v)."\n";
            } else {
                echo var_export($v, true)."\n";
            }
        }
    }

    echo "\n";
}
