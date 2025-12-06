<?php

// Usage: php scripts/fill_missing_names.php input.csv output.csv

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/fill_missing_names.php <input.csv> <output.csv>\n");
    exit(2);
}

$inPath = $argv[1];
$outPath = $argv[2];

if (! is_readable($inPath)) {
    fwrite(STDERR, "Input file not readable: $inPath\n");
    exit(3);
}

$in = new SplFileObject($inPath, 'r');
$out = fopen($outPath, 'w');
if ($out === false) {
    fwrite(STDERR, "Could not open output file: $outPath\n");
    exit(4);
}

// Read header
$header = $in->fgetcsv();
if ($header === false) {
    fwrite(STDERR, "Could not read header\n");
    exit(5);
}
fputcsv($out, $header);

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

if ($firstIdx === null && $familyIdx === null) {
    fwrite(STDERR, "Header missing both first_name and family_name columns; nothing to fill.\n");
    // still copy through
}

$total = 0;
$filled = 0;
while (! $in->eof()) {
    $row = $in->fgetcsv();
    if ($row === false || $row === null) {
        continue;
    }
    $total++;
    if ($firstIdx !== null) {
        $v = isset($row[$firstIdx]) ? trim($row[$firstIdx]) : '';
        if ($v === '') {
            $row[$firstIdx] = 'X';
            $filled++;
        }
    }
    if ($familyIdx !== null) {
        $v = isset($row[$familyIdx]) ? trim($row[$familyIdx]) : '';
        if ($v === '') {
            $row[$familyIdx] = 'X';
            $filled++;
        }
    }
    fputcsv($out, $row);
}

fclose($out);
fwrite(STDOUT, "Wrote filled CSV to: $outPath (total={$total}, filled_changes={$filled})\n");
exit(0);
