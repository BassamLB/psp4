<?php

// Usage: php scripts/export_db_sijil.php output_file
if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/export_db_sijil.php <output_file>\n");
    exit(2);
}

$out = $argv[1];
if (file_exists($out)) {
    unlink($out);
}

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = $app->make('db');
$db->table('voters')->orderBy('id')->chunk(1000, function ($rows) use ($out) {
    foreach ($rows as $r) {
        $val = isset($r->sijil_number) ? $r->sijil_number : '';
        file_put_contents($out, $val.PHP_EOL, FILE_APPEND);
    }
});

fwrite(STDOUT, "Exported DB keys to: $out\n");
exit(0);
