<?php

// Usage: php scripts/export_csv_keys.php input.csv output_keys.txt output_rows_no_sijil.csv
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/export_csv_keys.php <input.csv> <output_keys.txt> [output_rows_no_sijil.csv]\n");
    exit(2);
}

$inPath = $argv[1];
$outKeys = $argv[2];
$outNoSijil = isset($argv[3]) ? $argv[3] : null;

if (! is_readable($inPath)) {
    fwrite(STDERR, "Input not readable: $inPath\n");
    exit(3);
}

$in = new SplFileObject($inPath, 'r');
$keysOut = fopen($outKeys, 'w');
if ($keysOut === false) {
    fwrite(STDERR, "Could not open output keys file\n");
    exit(4);
}
$noSOut = $outNoSijil ? fopen($outNoSijil, 'w') : null;

$header = $in->fgetcsv();
if ($header === false) {
    fwrite(STDERR, "Could not read header\n");
    exit(5);
}

$indexMap = [];
foreach ($header as $i => $h) {
    $indexMap[mb_strtolower($h)] = $i;
}

$sijilIdx = $indexMap['sijil_number'] ?? null;
$firstIdx = $indexMap['first_name'] ?? null;
$familyIdx = $indexMap['family_name'] ?? null;
$dobIdx = $indexMap['date_of_birth'] ?? null;

if ($noSOut) {
    fputcsv($noSOut, $header);
}

$line = 1;
$written = 0;
$noS = 0;
while (! $in->eof()) {
    $line++;
    $row = $in->fgetcsv();
    if ($row === null || $row === false) {
        continue;
    }
    // build key: prefer sijil_number, otherwise composite first|family|dob
    $key = '';
    if ($sijilIdx !== null && isset($row[$sijilIdx]) && trim($row[$sijilIdx]) !== '') {
        $key = trim($row[$sijilIdx]);
    } else {
        $parts = [];
        if ($firstIdx !== null) {
            $parts[] = trim($row[$firstIdx] ?? '');
        }
        if ($familyIdx !== null) {
            $parts[] = trim($row[$familyIdx] ?? '');
        }
        if ($dobIdx !== null) {
            $parts[] = trim($row[$dobIdx] ?? '');
        }
        $key = implode('|', $parts);
    }
    fwrite($keysOut, $key.PHP_EOL);
    $written++;
    if (($sijilIdx === null || ! isset($row[$sijilIdx]) || trim($row[$sijilIdx]) === '') && $noSOut) {
        fputcsv($noSOut, $row);
        $noS++;
    }
}

if ($noSOut) {
    fclose($noSOut);
}
fclose($keysOut);

fwrite(STDOUT, "Exported $written keys to $outKeys (rows without sijil: $noS)\n");
exit(0);
