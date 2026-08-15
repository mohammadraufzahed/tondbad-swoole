<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Reporters;

use TondbadSwoole\Benchmark\BenchmarkResult;

interface Reporter
{
    /**
     * @param list<BenchmarkResult> $results
     */
    public function report(array $results, ?string $output = null): void;
}
