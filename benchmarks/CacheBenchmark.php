<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class CacheBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();
        cache()->set('key', 'value', 60);
    }

    public function benchGet(Blackhole $bh): void
    {
        $bh->consume(cache()->get('key'));
    }

    public function benchSet(Blackhole $bh): void
    {
        $bh->consume(cache()->set('key', 'value', 60));
    }
}
