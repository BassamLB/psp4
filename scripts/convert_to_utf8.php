<?php

// Usage: php convert_to_utf8.php [from-encoding]
$from = $argv[1] ?? 'Windows-1256';

// Normalize encoding name to one supported by mb_convert_encoding
function normalize_encoding(string $enc): string
{
    $list = array_map('strtoupper', mb_list_encodings());
    $candidate = strtoupper($enc);
    if (in_array($candidate, $list, true)) {
        return $candidate;
    }

    // Try common aliases (e.g., Windows-1256 -> CP1256)
    $alias = preg_replace('/[^A-Z0-9]/', '', strtoupper($enc));
    foreach ($list as $available) {
        if (str_replace(['-', '_'], '', strtoupper($available)) === $alias) {
            return $available;
        }
    }

    return $enc;
}

$from = normalize_encoding($from);
$src = __DIR__.'/../storage/app/private/imports/1764798952_Shouf-Indexed.csv';
$basename = basename($src);
$dst = __DIR__.'/../storage/app/private/imports/cleaned_fixed_'.$basename;

if (! file_exists($src)) {
    fwrite(STDERR, "Source file not found: {$src}\n");
    exit(1);
}

$in = new SplFileObject($src);
$out = fopen($dst, 'wb');
if ($out === false) {
    fwrite(STDERR, "Failed to open destination for writing: {$dst}\n");
    exit(1);
}

echo "Converting {$src} from {$from} -> UTF-8 and writing to {$dst}\n";
$count = 0;
while (! $in->eof()) {
    $line = $in->fgets();
    if ($line === false) {
        break;
    }

    // Prefer iconv when the requested encoding is not available to mb
    $mbList = mb_list_encodings();
    $foundMb = null;
    $norm = function ($s) {
        return str_replace(['-', '_'], '', strtoupper($s));
    };
    foreach ($mbList as $m) {
        if ($norm($m) === $norm($from)) {
            $foundMb = $m;
            break;
        }
    }

    if ($foundMb !== null) {
        $converted = @mb_convert_encoding($line, 'UTF-8', $foundMb);
    } else {
        $converted = @iconv($from, 'UTF-8//IGNORE', $line);
    }

    if ($converted === false) {
        // last resort: try some common encodings via iconv
        $cands = ['WINDOWS-1256', 'ISO-8859-6', 'CP1256', 'CP1252', 'ISO-8859-1'];
        foreach ($cands as $enc) {
            $converted = @iconv($enc, 'UTF-8//IGNORE', $line);
            if ($converted !== false) {
                break;
            }
        }
    }
    if ($converted === false) {
        $converted = $line;
    }

    fwrite($out, $converted);
    $count++;
}

fclose($out);
echo "Wrote {$count} lines to {$dst}\n";
