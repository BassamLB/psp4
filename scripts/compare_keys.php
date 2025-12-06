<?php

// Usage: php scripts/compare_keys.php csv_keys.txt db_keys.txt csv_source.csv output_missing.csv [sampleLimit]
if ($argc < 6) {
    fwrite(STDERR, "Usage: php scripts/compare_keys.php <csv_keys.txt> <db_keys.txt> <csv_source.csv> <output_missing.csv> [sampleLimit]\n");
    exit(2);
}

$csvKeys = $argv[1];
$dbKeys = $argv[2];
$csvSource = $argv[3];
$outMissing = $argv[4];
$sampleLimit = isset($argv[5]) ? (int) $argv[5] : 20;

if (! is_readable($csvKeys) || ! is_readable($dbKeys) || ! is_readable($csvSource)) {
    fwrite(STDERR, "Input files not readable\n");
    exit(3);
}

$dbSet = [];
$fh = fopen($dbKeys, 'r');
while (($line = fgets($fh)) !== false) {
    $k = rtrim($line, "\r\n");
    $dbSet[$k] = true;
}
fclose($fh);

$out = fopen($outMissing, 'w');
if ($out === false) {
    fwrite(STDERR, "Could not open output file\n");
    exit(4);
}

$in = new SplFileObject($csvSource, 'r');
$header = $in->fgetcsv();
fputcsv($out, $header);

$keysF = fopen($csvKeys, 'r');
$missing = 0;
$lineNo = 1;
$samples = [];
while (($k = fgets($keysF)) !== false) {
    $lineNo++;
    $key = rtrim($k, "\r\n");
    $row = $in->fgetcsv();
    if ($row === null || $row === false) {
        continue;
    }
    if (! isset($dbSet[$key])) {
        fputcsv($out, $row);
        $missing++;
        if (count($samples) < $sampleLimit) {
            $samples[] = ['line' => $lineNo, 'key' => $key, 'row' => $row];
        }
    }
}
fclose($keysF);
fclose($out);

fwrite(STDOUT, "Missing rows: $missing (written to: $outMissing)\n");
if ($missing > 0) {
    fwrite(STDOUT, "Sample missing rows:\n");
    foreach ($samples as $s) {
        $cellsShort = array_map(function ($c) {
            return mb_substr((string) $c, 0, 80);
        }, $s['row']);
        fwrite(STDOUT, "Line {$s['line']} key={$s['key']}: ".implode(' | ', $cellsShort)."\n");
    }
}

exit(0);
