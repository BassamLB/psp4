<?php

// Usage: php scripts/split_invalid_rows.php input.csv valid.csv invalid.csv
if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/split_invalid_rows.php <input.csv> <valid.csv> <invalid.csv>\n");
    exit(2);
}

$inPath = $argv[1];
$validPath = $argv[2];
$invalidPath = $argv[3];

if (! is_readable($inPath)) {
    fwrite(STDERR, "Input not readable: $inPath\n");
    exit(3);
}

$in = new SplFileObject($inPath, 'r');
$vOut = fopen($validPath, 'w');
$eOut = fopen($invalidPath, 'w');
if ($vOut === false || $eOut === false) {
    fwrite(STDERR, "Could not open output files\n");
    exit(4);
}

$header = $in->fgetcsv();
if ($header === false) {
    fwrite(STDERR, "Could not read header\n");
    exit(5);
}

fputcsv($vOut, $header);
fputcsv($eOut, $header);

$firstIdx = null;
$familyIdx = null;
foreach ($header as $i => $h) {
    $lh = mb_strtolower($h);
    if ($lh === 'first_name') {
        $firstIdx = $i;
    }
    if ($lh === 'family_name') {
        $familyIdx = $i;
    }
}

if ($firstIdx === null || $familyIdx === null) {
    fwrite(STDERR, "Header missing required columns first_name or family_name\n");
    exit(6);
}

$total = 0;
$valid = 0;
$invalid = 0;
while (! $in->eof()) {
    $row = $in->fgetcsv();
    if ($row === false || $row === null) {
        continue;
    }
    $total++;
    $first = isset($row[$firstIdx]) ? trim($row[$firstIdx]) : '';
    $family = isset($row[$familyIdx]) ? trim($row[$familyIdx]) : '';
    if ($first !== '' && $family !== '') {
        fputcsv($vOut, $row);
        $valid++;
    } else {
        fputcsv($eOut, $row);
        $invalid++;
    }
}

fclose($vOut);
fclose($eOut);

fwrite(STDOUT, "Split complete: total=$total valid=$valid invalid=$invalid\n");
exit(0);
