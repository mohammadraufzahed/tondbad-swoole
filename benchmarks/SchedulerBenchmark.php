<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Scheduling\Schedule;
use TondbadSwoole\Scheduling\ScheduleRegistry;
use TondbadSwoole\Scheduling\Scheduler;
use TondbadSwoole\Scheduling\Stores\MemoryScheduleStore;

#[Benchmark(warmup: 3, iterations: 100, invocations: 10)]
class SchedulerBenchmark
{
    private Schedule $schedule;
    private Scheduler $scheduler;

    #[Setup]
    public function setUp(): void
    {
        $app = BenchmarkApp::boot();

        $registry = new ScheduleRegistry();
        $this->scheduler = new Scheduler(
            new MemoryScheduleStore(),
            $registry,
            $app->container,
            $app->basePath(),
        );

        $this->schedule = new Schedule($this->scheduler, $app->container, $app->basePath(), $registry);

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
        foreach ($this->scheduler->store()->all() as $definition) {
            $definition->lastRunAt = null;
        }

        $bh->consume($this->schedule->runDueEvents(new DateTimeImmutable()));
    }
}
