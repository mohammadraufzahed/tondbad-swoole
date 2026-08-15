<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Reporters;

use TondbadSwoole\Benchmark\BenchmarkResult;

final class ConsoleReporter implements Reporter
{
    public function report(array $results, ?string $output = null): void
    {
        if ($results === []) {
            fwrite(STDOUT, "No benchmarks run.\n");

            return;
        }

        $rows = [];
        $headers = ['Benchmark', 'Mode', 'Cnt', 'Score', 'Error', 'Unit', 'Ops/s', 'Outliers'];

        foreach ($results as $result) {
            $unit = $result->timeUnit;
            $label = $unit->label();

            if ($result->mode->value === 'throughput') {
                $score = number_format($result->opsPerSecond, 2);
                $error = number_format($result->stddev / $result->mean * 100, 2) . '%';
                $unitLabel = 'ops/s';
            } else {
                $score = number_format($unit->fromNanos($result->mean), 3);
                $error = '±' . number_format($unit->fromNanos($result->ci95Upper - $result->mean), 3);
                $unitLabel = $label . '/op';
            }

            $rows[] = [
                $result->name,
                $result->mode->value,
                (string) ($result->iterations * $result->invocations),
                $score,
                $error,
                $unitLabel,
                number_format($result->opsPerSecond, 2),
                (string) $result->outliers,
            ];
        }

        $this->printTable($headers, $rows);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function printTable(array $headers, array $rows): void
    {
        $widths = [];

        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($cell));
            }
        }

        $this->printRow($headers, $widths);
        $this->printSeparator($widths);

        foreach ($rows as $row) {
            $this->printRow($row, $widths);
        }
    }

    /**
     * @param list<string> $row
     * @param array<int, int> $widths
     */
    private function printRow(array $row, array $widths): void
    {
        $parts = [];

        foreach ($row as $i => $cell) {
            $parts[] = str_pad($cell, $widths[$i] ?? 0, ' ', STR_PAD_LEFT);
        }

        fwrite(STDOUT, '  ' . implode('  ', $parts) . "\n");
    }

    /**
     * @param array<int, int> $widths
     */
    private function printSeparator(array $widths): void
    {
        $parts = [];

        foreach ($widths as $width) {
            $parts[] = str_repeat('-', $width);
        }

        fwrite(STDOUT, '  ' . implode('  ', $parts) . "\n");
    }
}
