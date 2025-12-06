<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImportBatch;
use App\Models\VoterUpload;

$upload = VoterUpload::latest()->first();
$batch = ImportBatch::latest()->first();

$out = [
    'upload' => $upload ? ['id' => $upload->id, 'status' => $upload->status, 'meta' => $upload->meta] : null,
    'batch' => $batch ? ['id' => $batch->id, 'voter_upload_id' => $batch->voter_upload_id, 'status' => $batch->status, 'options' => $batch->options, 'report' => $batch->report] : null,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
