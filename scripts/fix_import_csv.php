<?php

// Usage: php scripts/fix_import_csv.php input.csv output.csv

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/fix_import_csv.php <input.csv> <output.csv>\n");
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

// Read header line and normalize
$header = $in->fgetcsv();
if ($header === null || $header === false) {
    fwrite(STDERR, "Could not read header from input file\n");
    exit(5);
}

// Remove UTF-8 BOM / unusual leading chars from first header cell and from any header names
foreach ($header as $k => $h) {
    if (! is_string($h)) {
        $header[$k] = $h;

        continue;
    }
    // Remove common BOM sequences and unicode FEFF
    $h = preg_replace('/^\xEF\xBB\xBF/u', '', $h);
    $h = preg_replace('/\x{FEFF}/u', '', $h);
    // Also remove any leading invisible/garbled characters (keeps letters, numbers, space, underscore, and Arabic letters)
    $h = preg_replace('/^[^\p{L}\p{N}_]+/u', '', $h);
    $h = trim($h);
    $header[$k] = $h;
}

// Normalize header keys to expected english-like keys where possible (non-destructive)
$normalizeMap = [
    'first_name' => 'first_name',
    'ï»؟first_name' => 'first_name',
    'family_name' => 'family_name',
    'father_name' => 'father_name',
    'mother_full_name' => 'mother_full_name',
    'date_of_birth' => 'date_of_birth',
    'personal_sect' => 'personal_sect',
    'gender' => 'gender',
    'gender_id' => 'gender_id',
    'sijil_number' => 'sijil_number',
    'sijil_additional_string' => 'sijil_additional_string',
    'sect' => 'sect',
    'sect_id' => 'sect_id',
];

foreach ($header as $i => $h) {
    $low = mb_strtolower($h);
    if (isset($normalizeMap[$low])) {
        $header[$i] = $normalizeMap[$low];

        continue;
    }
    // If header contains spaces, replace with underscore
    $header[$i] = str_replace(' ', '_', $h);
}

// Write normalized header (no BOM)
fputcsv($out, $header);

// Find date_of_birth index if present
$dateIdx = null;
foreach ($header as $i => $h) {
    $lh = mb_strtolower($h);
    if (strpos($lh, 'date') !== false && strpos($lh, 'birth') !== false) {
        $dateIdx = $i;
        break;
    }
    if ($lh === 'date_of_birth') {
        $dateIdx = $i;
        break;
    }
}

$row = 1; // header already consumed
while (! $in->eof()) {
    $row++;
    $data = $in->fgetcsv();
    if ($data === false || $data === null) {
        continue;
    }
    // Ensure same number of columns as header
    if (count($data) < count($header)) {
        // pad
        $data = array_pad($data, count($header), '');
    }

    // Convert encoding of each field to UTF-8 if needed (try to detect)
    foreach ($data as $k => $v) {
        if (! is_string($v)) {
            continue;
        }
        // replace BOM-like garble at fields start
        $v = preg_replace('/^\xEF\xBB\xBF/u', '', $v);
        $v = preg_replace('/\x{FEFF}/u', '', $v);
        $v = trim($v);
        $data[$k] = $v;
    }

    // Normalize date format if needed
    if ($dateIdx !== null && isset($data[$dateIdx]) && $data[$dateIdx] !== '') {
        $d = $data[$dateIdx];
        // If already YYYY-MM-DD, keep
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 0) {
            // Try DD/MM/YYYY or DD-MM-YYYY
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $d, $m)) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mon = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year = $m[3];
                // Basic sanity check
                if (checkdate((int) $mon, (int) $day, (int) $year)) {
                    $data[$dateIdx] = "$year-$mon-$day";
                }
            }
        }
    }

    fputcsv($out, $data);
}

fclose($out);

fwrite(STDOUT, "Wrote fixed CSV to: $outPath\n");
exit(0);
