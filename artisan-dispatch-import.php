<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Jobs\ImportVotersJob::dispatch('storage/app/private/imports/shouf.csv', [
    'batch' => 1000,
    'skip_header' => true,
    'show_unmatched' => true,
])->onQueue('imports_queue');

echo "DISPATCHED\n";
