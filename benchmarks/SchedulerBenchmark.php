<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Scheduling\Schedule;

#[Benchmark(warmup: 3, iterations: 100, invocations: 10)]
class SchedulerBenchmark
{
    private Schedule $schedule;

    #[Setup]
    public function setUp(): void
    {
        $app = BenchmarkApp::boot();

        $this->schedule = new Schedule($app->container, $app->basePath());

        for ($i = 0; $i < 50; $i++) {
            $this->schedule->call(fn () => null)->everyMinute();
        }
    }

    public function benchDueEvents(Blackhole $bh): void
    {
        $bh->consume(count($this->schedule->dueEvents(new DateTimeImmutable())));
    }

    public function benchRunDueEvents(Blackhole $bh): void
    {
        $bh->consume($this->schedule->runDueEvents(new DateTimeImmutable()));
    }
}
