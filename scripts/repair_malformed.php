<?php

// Usage: php scripts/repair_malformed.php input.csv output.csv
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/repair_malformed.php <input.csv> <output.csv>\n");
    exit(2);
}

$inPath = $argv[1];
$outPath = $argv[2];

if (! is_readable($inPath)) {
    fwrite(STDERR, "Input not readable: $inPath\n");
    exit(3);
}

$in = new SplFileObject($inPath, 'r');
$out = fopen($outPath, 'w');
if ($out === false) {
    fwrite(STDERR, "Could not open output file: $outPath\n");
    exit(4);
}

$header = $in->fgetcsv();
if ($header === false) {
    fwrite(STDERR, "Could not read header\n");
    exit(5);
}
fputcsv($out, $header);

$headerCount = count($header);
$line = 1;
$repaired = 0;
$skipped = 0;
while (! $in->eof()) {
    $line++;
    $row = $in->fgetcsv();
    if ($row === null || $row === false) {
        continue;
    }
    // If fully blank line (single empty cell or all empty), skip
    $allEmpty = true;
    foreach ($row as $c) {
        if (is_string($c) && trim($c) !== '') {
            $allEmpty = false;
            break;
        }
    }
    if ($allEmpty) {
        $skipped++;

        continue;
    }

    $cnt = count($row);
    if ($cnt < $headerCount) {
        // pad to headerCount
        $row = array_pad($row, $headerCount, '');
        $repaired++;
    } elseif ($cnt > $headerCount) {
        // truncate extra columns
        $row = array_slice($row, 0, $headerCount);
    }
    fputcsv($out, $row);
}

fclose($out);
fwrite(STDOUT, "Wrote repaired CSV to: $outPath (repaired={$repaired}, skipped_blank={$skipped})\n");
exit(0);
