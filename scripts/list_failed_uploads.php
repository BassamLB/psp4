<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\VoterUpload;

$failed = VoterUpload::where('status', 'clean_failed')->orderBy('id', 'desc')->get();
if ($failed->isEmpty()) {
    echo "No uploads with status=clean_failed\n";
    exit(0);
}

foreach ($failed as $u) {
    echo "id={$u->id} filename={$u->filename} status={$u->status}\n";
    echo json_encode($u->meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";
}

exit(0);
