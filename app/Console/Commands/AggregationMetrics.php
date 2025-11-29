<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AggregationMetrics extends Command
{
    protected $signature = 'aggregation:metrics {--minutes=60 : Lookback window in minutes} {--top=10 : Top stations to show} {--file= : Optional log file to parse (relative to storage/logs) }';

    protected $description = 'Report aggregation job metrics (lock contention, durations) by parsing recent Laravel logs.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $top = (int) $this->option('top');

        $fileOption = $this->option('file');
        if ($fileOption) {
            $candidate = base_path($fileOption);
            // allow passing storage/logs/laravel.log or just laravel.log
            if (! file_exists($candidate)) {
                $candidate = storage_path('logs'.DIRECTORY_SEPARATOR.ltrim($fileOption, DIRECTORY_SEPARATOR));
            }
            if (! file_exists($candidate)) {
                $this->error("Log file not found: {$fileOption}");

                return 1;
            }
            $logPath = $candidate;
        } else {
            $logPath = $this->latestLogFile();
        }
        if (! $logPath) {
            $this->error('No log files found in storage/logs');

            return 1;
        }

        $this->info("Analyzing log: {$logPath} (last {$minutes} minutes)");

        $cutoff = (new \DateTime)->modify("-{$minutes} minutes");

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->error('Failed to read log file');

            return 1;
        }

        $starts = [];
        $completes = [];
        $contentions = [];
        $failures = 0;

        foreach ($lines as $line) {
            // Expect format: [2025-11-27 12:34:56] local.INFO: Message {"station_id":123}
            if (! preg_match('/^\[([^\]]+)\]\s+[^:]+:\s+(.*)$/', $line, $m)) {
                continue;
            }

            // Try parsing with microseconds first, fallback to second-precision
            $ts = \DateTime::createFromFormat('Y-m-d H:i:s.u', $m[1]);
            if (! $ts) {
                $ts = \DateTime::createFromFormat('Y-m-d H:i:s', substr($m[1], 0, 19));
            }
            if (! $ts) {
                continue;
            }

            if ($ts < $cutoff) {
                continue;
            }

            $msg = $m[2];

            // If the logger included a high-resolution ts field in the message JSON context,
            // prefer that timestamp (it will be in microsecond precision like 2025-11-27 12:34:56.123456).
            if (preg_match('/"ts"\s*:\s*"([0-9:\-\. ]+\.[0-9]+)"/', $msg, $tm)) {
                $maybe = \DateTime::createFromFormat('Y-m-d H:i:s.u', $tm[1]);
                if ($maybe) {
                    $ts = $maybe;
                }
            }

            // Also support a plain-text `ts=YYYY-mm-dd HH:MM:SS.u` token inside the message
            // (added for quick visibility). Example: "Starting aggregation for station ts=2025-11-27 12:34:56.123456"
            if (preg_match('/ts=([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+)/', $msg, $tm2)) {
                $maybe2 = \DateTime::createFromFormat('Y-m-d H:i:s.u', $tm2[1]);
                if ($maybe2) {
                    $ts = $maybe2;
                }
            }

            if (str_contains($msg, 'Starting aggregation for station')) {
                $stationId = $this->extractStationId($msg);
                if ($stationId) {
                    $starts[$stationId][] = $ts;
                }
            } elseif (str_contains($msg, 'Aggregation completed successfully for station')) {
                $stationId = $this->extractStationId($msg);
                if ($stationId) {
                    $completes[$stationId][] = $ts;
                }
            } elseif (str_contains($msg, 'Aggregation already in progress for station')) {
                $stationId = $this->extractStationId($msg);
                if ($stationId) {
                    $contentions[$stationId] = ($contentions[$stationId] ?? 0) + 1;
                }
            } elseif (str_contains($msg, 'Failed to aggregate station results')) {
                $failures++;
            }
        }

        // Pair starts and completes to compute durations
        $durations = [];
        foreach ($starts as $stationId => $startTimes) {
            $completeTimes = $completes[$stationId] ?? [];
            // sort to be safe
            usort($startTimes, fn ($a, $b) => $a <=> $b);
            usort($completeTimes, fn ($a, $b) => $a <=> $b);

            $ci = 0;
            foreach ($startTimes as $st) {
                // find first completion after start
                while (isset($completeTimes[$ci]) && $completeTimes[$ci] < $st) {
                    $ci++;
                }
                if (isset($completeTimes[$ci]) && $completeTimes[$ci] >= $st) {
                    // Compute high-resolution interval in seconds (float) then convert to ms
                    $startFloat = (float) $st->format('U.u');
                    $completeFloat = (float) $completeTimes[$ci]->format('U.u');
                    $intervalSec = $completeFloat - $startFloat;
                    $durations[$stationId][] = (int) round($intervalSec * 1000); // milliseconds
                    $ci++;
                }
            }
        }

        $totalStarts = array_sum(array_map(fn ($a) => count($a), $starts));
        $totalCompletes = array_sum(array_map(fn ($a) => count($a), $completes));
        $totalContentions = array_sum($contentions);

        $allDurations = [];
        foreach ($durations as $d) {
            foreach ($d as $sec) {
                $allDurations[] = $sec;
            }
        }

        // durations are stored in milliseconds
        $avg = count($allDurations) ? array_sum($allDurations) / count($allDurations) : 0;
        sort($allDurations);
        $median = count($allDurations) ? $allDurations[(int) floor(count($allDurations) / 2)] : 0;

        $this->info('Aggregation Metrics');
        $this->line('-------------------');
        $this->line("Window: last {$minutes} minutes");
        $this->line("Starts: {$totalStarts}");
        $this->line("Completes: {$totalCompletes}");
        $this->line("Failures (exceptions): {$failures}");
        $this->line("Lock contentions (failed to acquire): {$totalContentions}");
        $this->line(sprintf('Avg duration: %.2f ms, median: %d ms', $avg, $median));

        // Top stations by avg duration
        $stationStats = [];
        foreach ($durations as $sid => $vals) {
            $stationStats[$sid] = [
                'count' => count($vals),
                'avg' => array_sum($vals) / count($vals),
                'contentions' => $contentions[$sid] ?? 0,
            ];
        }

        uasort($stationStats, fn ($a, $b) => $b['avg'] <=> $a['avg']);

        $headers = ['station_id', 'avg_ms', 'runs', 'contentions'];
        $rows = [];
        foreach (array_slice($stationStats, 0, $top, true) as $sid => $s) {
            $rows[] = [$sid, (int) round($s['avg']), $s['count'], $s['contentions']];
        }

        if (! empty($rows)) {
            $this->table($headers, $rows);
        } else {
            $this->line('No completed aggregations found in window.');
        }

        return 0;
    }

    protected function latestLogFile(): ?string
    {
        $dir = storage_path('logs');
        if (! is_dir($dir)) {
            return null;
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.log');
        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return $files[0];
    }

    protected function extractStationId(string $msg): ?int
    {
        // Try to find "station_id":123 in the message JSON context
        if (preg_match('/"station_id"\s*:\s*(\d+)/', $msg, $m)) {
            return (int) $m[1];
        }

        // Fallback: try to match station_id=123
        if (preg_match('/station_id["= ]+(\d+)/', $msg, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
