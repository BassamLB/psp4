<?php

use App\Jobs\ImportVotersToTempTable;
use App\Models\VoterUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('marks upload error when cleaned_path missing', function () {
    // Use the database (RefreshDatabase is configured globally in TestCase)
    $upload = VoterUpload::create([
        'filename' => 'test.csv',
        'original_name' => 'test.csv',
        'path' => 'imports/test.csv',
        'size' => 0,
        'status' => 'queued',
    ]);

    $job = new ImportVotersToTempTable($upload, []);
    $job->handle();

    $upload->refresh();

    expect($upload->status)->toBe('error');
    expect($upload->meta)->toBeArray();
    expect($upload->meta['import_error'] ?? null)->toBe('cleaned_path_missing');
});
