<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $redis = Illuminate\Support\Facades\Redis::connection();
    $result = $redis->ping();
    echo "✓ Redis: CONNECTED and WORKING\n";
    echo '  Ping response: '.($result === true ? 'PONG' : $result)."\n";

    // Test set/get
    $redis->set('test_key', 'Laravel + Redis working!');
    $value = $redis->get('test_key');
    echo "  Test value: {$value}\n";
    $redis->del('test_key');

} catch (\Exception $e) {
    echo "✗ Redis: NOT AVAILABLE\n";
    echo '  Error: '.$e->getMessage()."\n";
}
