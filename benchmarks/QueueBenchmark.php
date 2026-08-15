<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Queue\Jobs\Job;

class NoopQueueJob extends Job
{
    public function handle(): void {}
}

#[Benchmark(warmup: 3, iterations: 100, invocations: 10)]
class QueueBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();
        BenchmarkApp::migrate();

        for ($i = 0; $i < 10000; $i++) {
            queue()->push(new NoopQueueJob());
        }
    }

    public function benchPush(Blackhole $bh): void
    {
        $bh->consume(queue()->push(new NoopQueueJob()));
    }

    public function benchPop(Blackhole $bh): void
    {
        $bh->consume(queue()->pop());
    }
}
