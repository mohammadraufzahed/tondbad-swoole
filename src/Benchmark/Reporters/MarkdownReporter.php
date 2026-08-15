<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Reporters;

use TondbadSwoole\Benchmark\BenchmarkResult;

final class MarkdownReporter implements Reporter
{
    public function report(array $results, ?string $output = null): void
    {
        $lines = ['| Benchmark | Mode | Iterations | Score | Error | Unit | Ops/s | Outliers |', '|---|---|---|---|---|---|---|---|'];

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

            $lines[] = sprintf(
                '| %s | %s | %d | %s | %s | %s | %s | %d |',
                str_replace('|', '\\|', $result->name),
                $result->mode->value,
                $result->iterations * $result->invocations,
                $score,
                $error,
                $unitLabel,
                number_format($result->opsPerSecond, 2),
                $result->outliers,
            );
        }

        $markdown = implode("\n", $lines) . "\n";

        if ($output === null) {
            fwrite(STDOUT, $markdown);

            return;
        }

        file_put_contents($output, $markdown);
    }
}
