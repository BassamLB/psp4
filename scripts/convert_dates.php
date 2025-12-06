<?php

// Usage: php scripts/convert_dates.php input.csv output.csv
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/convert_dates.php input.csv output.csv\n");
    exit(2);
}

$in = $argv[1];
$out = $argv[2];

if (! is_readable($in)) {
    fwrite(STDERR, "Input file not readable: $in\n");
    exit(3);
}

$input = new SplFileObject($in, 'r');
$input->setFlags(SplFileObject::READ_CSV);

$output = new SplFileObject($out, 'w');

$line = 0;
while (! $input->eof()) {
    $row = $input->fgetcsv();
    if ($row === false) {
        $line++;

        continue;
    }

    // On some CSVs empty line returns [null] or [false]
    if ($row === [null] || $row === null) {
        $line++;

        continue;
    }

    // First line is header, just write through
    if ($line === 0) {
        $output->fputcsv($row);
        $line++;

        continue;
    }

    // date_of_birth is expected to be column 4 (0-based index)
    $idx = 4;
    if (isset($row[$idx]) && $row[$idx] !== null && trim($row[$idx]) !== '') {
        $d = trim($row[$idx]);
        // Accept formats: d/m/Y or d-m-Y or Y-m-d
        $parsed = null;
        // Try Y-m-d first (already correct)
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if ($dt && $dt->format('Y-m-d') === $d) {
            $parsed = $d;
        } else {
            $dt = DateTime::createFromFormat('d/m/Y', $d);
            if ($dt) {
                $parsed = $dt->format('Y-m-d');
            } else {
                $dt = DateTime::createFromFormat('d-m-Y', $d);
                if ($dt) {
                    $parsed = $dt->format('Y-m-d');
                }
            }
        }

        if ($parsed !== null) {
            $row[$idx] = $parsed;
        } else {
            // leave as-is; importer may handle or log
            $row[$idx] = $d;
        }
    }

    $output->fputcsv($row);
    $line++;
}

// SplFileObject does not require fclose; just report completion
echo "Converted dates written to: $out\n";
