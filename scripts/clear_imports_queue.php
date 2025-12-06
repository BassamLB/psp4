<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    if (! app()->bound('redis')) {
        echo "ERR: Redis service not bound in container. Check your environment and QUEUE_CONNECTION.\n";
        exit(1);
    }

    $redis = app('redis');

    // If the manager was returned, try to get a connection
    if (method_exists($redis, 'connection')) {
        try {
            $redis = $redis->connection();
        } catch (Throwable $e) {
            // ignore and proceed with original object
        }
    }

    $key = 'queues:imports';

    // Attempt to get length using common methods, fallback to raw command where available
    if (is_callable([$redis, 'llen'])) {
        $before = $redis->llen($key);
    } elseif (is_callable([$redis, 'command'])) {
        $before = $redis->command('LLEN', [$key]);
    } else {
        throw new RuntimeException('Redis client does not support LLEN or command().');
    }

    echo "Before: $key length = $before\n";

    if (is_callable([$redis, 'del'])) {
        $redis->del($key);
    } elseif (is_callable([$redis, 'command'])) {
        $redis->command('DEL', [$key]);
    } else {
        throw new RuntimeException('Redis client does not support DEL or command().');
    }

    if (is_callable([$redis, 'llen'])) {
        $after = $redis->llen($key);
    } elseif (is_callable([$redis, 'command'])) {
        $after = $redis->command('LLEN', [$key]);
    } else {
        $after = 0;
    }

    echo "After: $key length = $after\n";
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}

echo "Done.\n";
