<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
// Bootstrap the application kernel so facades and service container are available
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $redis = app('redis');
    $ping = $redis->ping();
    echo 'PING: ';
    var_export($ping);
    echo PHP_EOL;

    $len = $redis->llen('queues:imports');
    echo 'LLEN queues:imports: ';
    var_export($len);
    echo PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
}
