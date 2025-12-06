<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$redis = app('redis');

$len = $redis->llen('queues:imports');
echo "queues:imports length = $len\n";
$show = min(10, max(0, $len - 1));
for ($i = 0; $i < min(10, $len); $i++) {
    $payload = $redis->lindex('queues:imports', $i);
    echo "--- item $i ---\n";
    if ($payload === null) {
        echo "(null)\n";

        continue;
    }
    // payload is serialized command in Laravel: JSON containing 'displayName' and 'data'->'command'
    echo 'raw: '.substr($payload, 0, 200)."...\n";
    // try to json decode
    $obj = @json_decode($payload, true);
    if (is_array($obj)) {
        echo 'displayName: '.($obj['displayName'] ?? 'N/A')."\n";
        if (isset($obj['data']['commandName'])) {
            echo 'commandName: '.$obj['data']['commandName']."\n";
        }
        if (isset($obj['data']['command'])) {
            echo 'command (truncated): '.substr($obj['data']['command'], 0, 200)."\n";
        }
    } else {
        echo "not json\n";
    }
}
