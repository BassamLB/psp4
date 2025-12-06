<?php

// Usage: php scripts/find_malformed_rows.php input.csv output_malformed.csv [sampleLimit]
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/find_malformed_rows.php <input.csv> <output_malformed.csv> [sampleLimit]\n");
    exit(2);
}

$inPath = $argv[1];
$outPath = $argv[2];
$sampleLimit = isset($argv[3]) ? (int) $argv[3] : 20;

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

$headerCount = count($header);
fputcsv($out, array_merge(['_line_number'], $header));

$lineNo = 1; // header
$malformed = 0;
$samples = [];
while (! $in->eof()) {
    $lineNo++;
    $row = $in->fgetcsv();
    if ($row === null || $row === false) {
        continue;
    }
    // Some CSV readers may return an array with a single null/empty when blank lines exist
    if (count($row) < $headerCount) {
        $malformed++;
        if (count($samples) < $sampleLimit) {
            $samples[] = ['line' => $lineNo, 'cols' => count($row), 'row' => $row];
        }
        // pad to headerCount so CSV stays consistent
        $padded = array_pad($row, $headerCount, '');
        fputcsv($out, array_merge([$lineNo], $padded));
    }
}

fclose($out);

fwrite(STDOUT, "Scanned: $lineNo lines. Header columns: $headerCount. Malformed rows: $malformed\n");
if ($malformed > 0) {
    fwrite(STDOUT, "Saved malformed rows to: $outPath\n");
    fwrite(STDOUT, "Sample malformed rows (up to $sampleLimit):\n");
    foreach ($samples as $s) {
        $cells = array_map(function ($c) {
            return (string) $c;
        }, $s['row']);
        $cellsShort = array_map(function ($c) {
            return mb_substr($c, 0, 60);
        }, $cells);
        fwrite(STDOUT, "Line {$s['line']} (cols={$s['cols']}): ".implode(' | ', $cellsShort)."\n");
    }
}

exit(0);
