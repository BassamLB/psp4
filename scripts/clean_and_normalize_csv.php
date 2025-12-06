<?php

// Usage:
// php scripts/clean_and_normalize_csv.php input.csv output.csv [--date-col=4] [--expected-cols=17] [--skip-header] [--delimiter=,]
// Produces: cleaned CSV at output path and invalid rows at storage/app/private/imports/<input>.invalid_rows.csv

if ($argc < 3) {
    echo "Usage: php scripts/clean_and_normalize_csv.php input.csv output.csv [--date-col=N] [--expected-cols=M] [--skip-header] [--delimiter=,]\n";
    exit(1);
}

// Resolve input and output paths; allow passing a basename as stored in private imports
$input = $argv[1];
$output = $argv[2];

// If input not found as given, try storage private paths
if (! file_exists($input)) {
    $alt = __DIR__.'/../storage/app/private/'.ltrim($input, '\\/');
    $alt2 = __DIR__.'/../storage/app/private/imports/'.ltrim($input, '\\/');
    if (file_exists($alt)) {
        $input = $alt;
    } elseif (file_exists($alt2)) {
        $input = $alt2;
    }
}

// Prevent accidental overwrite: if output resolves to same path as input, choose cleaned_<basename>
if (realpath($output) === realpath($input)) {
    $output = __DIR__.'/../storage/app/private/imports/cleaned_'.pathinfo($input, PATHINFO_BASENAME);
}

// If output is not an absolute path and its directory doesn't exist, write into private/imports
if (! is_dir(dirname($output))) {
    $output = __DIR__.'/../storage/app/private/imports/cleaned_'.pathinfo($input, PATHINFO_BASENAME);
}

$opts = getopt('', ['date-col::', 'expected-cols::', 'skip-header', 'delimiter::']);
$dateCol = isset($opts['date-col']) ? (int) $opts['date-col'] : null; // 0-based
$expectedCols = isset($opts['expected-cols']) ? (int) $opts['expected-cols'] : null;
$skipHeader = isset($opts['skip-header']);
$delimiter = isset($opts['delimiter']) ? $opts['delimiter'] : ',';

if (! file_exists($input)) {
    echo "Input file not found: $input\n";
    exit(1);
}

$in = new SplFileObject($input, 'r');
$in->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
// Use standard CSV controls: delimiter, enclosure '"', and allow default escape (backslash)
$in->setCsvControl($delimiter, '"', '\\');

// Ensure output directory exists
$outDir = dirname($output);
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$outFh = fopen($output, 'w');
if (! $outFh) {
    echo "Unable to open output file: $output\n";
    exit(1);
}

// Write invalid rows into the private imports folder (ensure exists)
$invalidDir = __DIR__.'/../storage/app/private/imports';
if (! is_dir($invalidDir)) {
    mkdir($invalidDir, 0755, true);
}

$invalidPath = $invalidDir.'/'.pathinfo($input, PATHINFO_FILENAME).'.invalid_rows.csv';
$invalidFh = fopen($invalidPath, 'w');

function clean_field(string $s): string
{
    // Try to detect source encoding from a list of expected encodings
    // If already valid UTF-8, leave as-is
    if ($s !== '' && mb_check_encoding($s, 'UTF-8')) {
        // nothing
    } else {
        $converted = false;

        // Try common Arabic / Windows encodings via iconv first (more permissive)
        $cands = ['WINDOWS-1256', 'CP1256', 'ISO-8859-6', 'CP1252', 'ISO-8859-1'];
        foreach ($cands as $enc) {
            $res = @iconv($enc, 'UTF-8//IGNORE', $s);
            if ($res !== false && $res !== '') {
                $s = $res;
                $converted = true;
                break;
            }
        }

        // If iconv didn't help, try mb_detect against available encodings
        if (! $converted) {
            $supported = mb_list_encodings();
            $detected = @mb_detect_encoding($s, $supported, true);
            if ($detected !== false && strtoupper($detected) !== 'UTF-8') {
                $res = @mb_convert_encoding($s, 'UTF-8', $detected);
                if ($res !== false) {
                    $s = $res;
                    $converted = true;
                }
            }
        }

        // Last resort: attempt to strip non-ASCII/high-bytes to avoid crashing downstream
        if (! $converted && ! mb_check_encoding($s, 'UTF-8')) {
            $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
        }
    }

    // Remove BOM and common invisible characters
    $s = preg_replace('/^[\x{FEFF}\x00-\x1F\x7F]+/u', '', $s);
    $s = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}]/u', '', $s);
    // Trim whitespace
    $s = trim($s);

    return $s;
}

function normalize_date_field(string $s): ?string
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    // Common formats: DD/MM/YYYY or D/M/YYYY or YYYY-MM-DD
    // First try ISO
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $s, $m)) {
        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        if ($year < 100) {
            $year += ($year > 50) ? 1900 : 2000;
        }
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return null;
    }
    // Try other parse
    $dt = date_create($s);
    if ($dt !== false) {
        return $dt->format('Y-m-d');
    }

    return null;
}

$rowNum = 0;
$cleaned = 0;
$invalid = 0;
$headerCols = null;

foreach ($in as $row) {
    // SplFileObject->READ_CSV may return null/false or array — skip non-arrays
    if (! is_array($row)) {
        continue;
    }
    // skip the special empty row marker sometimes returned
    if (count($row) === 1 && $row[0] === null) {
        continue;
    }

    $rowNum++;

    // Clean fields
    $fields = [];
    foreach ($row as $f) {
        $fields[] = $f === null ? '' : clean_field((string) $f);
    }

    // On first row, capture header length
    if ($rowNum === 1) {
        $headerCols = count($fields);
        if ($skipHeader) {
            fputcsv($outFh, $fields);

            continue;
        }
    }

    // If expectedCols provided, validate
    $colsOk = true;
    $expected = $expectedCols ?? $headerCols;
    if ($expected !== null && count($fields) !== $expected) {
        $colsOk = false;
        $reason = 'column_count_mismatch';
    }

    // Date normalization if requested and present
    if ($dateCol !== null) {
        if (! isset($fields[$dateCol])) {
            $colsOk = false;
            $reason = 'missing_date_column';
        } else {
            $normalized = normalize_date_field($fields[$dateCol]);
            if ($normalized === null) {
                $colsOk = false;
                $reason = 'invalid_date';
            } else {
                $fields[$dateCol] = $normalized;
            }
        }
    }

    if (! $colsOk) {
        $invalid++;
        // write to invalid file with reason
        $out = $fields;
        $out[] = $reason ?? 'invalid';
        fputcsv($invalidFh, $out);

        continue;
    }

    // Everything OK — write cleaned row
    fputcsv($outFh, $fields);
    $cleaned++;
}

// If there were no invalid rows, write a small placeholder so the file isn't empty
// (some spreadsheet apps cannot open a 0-byte file).
if ($invalid === 0) {
    // single-column human-readable placeholder
    fputcsv($invalidFh, ['No invalid rows']);
}

fclose($outFh);
fclose($invalidFh);

echo "Processed rows: $rowNum\n";
echo "Cleaned rows: $cleaned\n";
echo "Invalid rows: $invalid (see $invalidPath)\n";
echo "Wrote cleaned CSV to: $output\n";

exit(0);
