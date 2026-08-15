<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

final class BenchmarkComparator
{
    public function __construct(
        private readonly float $threshold = 0.05,
    ) {
    }

    /**
     * @param array<string, BenchmarkResult> $baseline
     * @param list<BenchmarkResult> $current
     * @return list<array<string, mixed>>
     */
    public function compare(array $baseline, array $current): array
    {
        $regressions = [];

        foreach ($current as $result) {
            $previous = $baseline[$result->name] ?? null;

            if ($previous === null) {
                continue;
            }

            $previousMean = $previous->mean;
            $currentMean = $result->mean;

            if ($previousMean <= 0) {
                continue;
            }

            $delta = ($currentMean - $previousMean) / $previousMean;

            if ($delta > $this->threshold) {
                $regressions[] = [
                    'benchmark' => $result->name,
                    'previous' => $previousMean,
                    'current' => $currentMean,
                    'delta' => $delta,
                ];
            }
        }

        return $regressions;
    }
}
