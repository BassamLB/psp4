<?php

$orig = __DIR__.'/../storage/app/private/imports/1764798952_Shouf-Indexed.csv';
$clean = __DIR__.'/../storage/app/private/imports/cleaned_1764798952_Shouf-Indexed.csv';

function head(string $path, int $n = 30): void
{
    echo "=== HEAD: {$path} ===\n";
    if (! file_exists($path)) {
        echo "MISSING\n";

        return;
    }

    $f = new SplFileObject($path);
    for ($i = 0; $i < $n && ! $f->eof(); $i++) {
        $line = rtrim($f->fgets(), "\r\n");
        printf("%03d %s\n", $i + 1, $line);
    }
}

function counts(string $path, int $n = 20): void
{
    echo "=== COUNTS: {$path} ===\n";
    if (! file_exists($path)) {
        echo "MISSING\n";

        return;
    }

    $f = new SplFileObject($path);
    for ($i = 0; $i < $n && ! $f->eof(); $i++) {
        $line = rtrim($f->fgets(), "\r\n");
        $fields = str_getcsv($line);
        printf("%03d %d\n", $i + 1, count($fields));
    }
}

head($orig, 30);
head($clean, 30);
counts($orig, 20);
counts($clean, 20);
