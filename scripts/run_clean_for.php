<?php

// Usage: php scripts/run_clean_for.php <upload_id>

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if ($argc < 2) {
    echo "Usage: php scripts/run_clean_for.php <upload_id>\n";
    exit(1);
}

$id = (int) $argv[1];
$upload = App\Models\VoterUpload::find($id);
if (! $upload) {
    echo "Upload not found: $id\n";
    exit(1);
}

echo "Running cleaner for upload id={$upload->id} filename={$upload->filename}\n";
App\Jobs\CleanVoterUpload::dispatchSync($upload);

echo json_encode($upload->fresh()->toArray(), JSON_PRETTY_PRINT)."\n";

exit(0);
