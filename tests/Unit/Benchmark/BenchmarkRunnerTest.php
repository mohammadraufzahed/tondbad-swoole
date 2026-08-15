<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\Benchmark;
use TondbadSwoole\Benchmark\BenchmarkResult;
use TondbadSwoole\Benchmark\BenchmarkRunner;
use TondbadSwoole\Benchmark\Scenario;
use TondbadSwoole\Benchmark\TimeUnit;

it('runs a simple callable benchmark', function () {
    $counter = 0;

    $result = Benchmark::measure('increment')
        ->warmup(2)
        ->iterations(100)
        ->run(function () use (&$counter): void {
            $counter++;
        });

    expect($result)->toBeInstanceOf(BenchmarkResult::class);
    expect($result->name)->toBe('increment');
    expect($result->iterations)->toBe(100);
    expect($result->mean)->toBeGreaterThan(0.0);
    expect($counter)->toBeGreaterThanOrEqual(100);
});

it('runs a benchmark with a blackhole', function () {
    $result = Benchmark::measure('blackhole')
        ->warmup(1)
        ->iterations(50)
        ->run(function ($bh) {
            $value = 1 + 1;
            $bh->consume($value);
        });

    expect($result->iterations)->toBe(50);
    expect($result->mean)->toBeGreaterThan(0.0);
});

it('supports custom time units', function () {
    $result = Benchmark::measure('unit')
        ->warmup(1)
        ->iterations(10)
        ->timeUnit(TimeUnit::Nanoseconds)
        ->run(fn () => null);

    expect($result->timeUnit)->toBe(TimeUnit::Nanoseconds);
});

it('runs a class-based scenario with setup', function () {
    $scenario = new Scenario(
        name: 'class-benchmark',
        warmup: 1,
        iterations: 10,
        class: ClassBasedBench::class,
        method: 'bench',
        setupMethod: 'setUp',
    );

    $result = (new BenchmarkRunner())->run($scenario);

    expect($result->name)->toBe('class-benchmark');
    expect($result->iterations)->toBe(10);
    expect(ClassBasedBench::$ready)->toBeTrue();
});

class ClassBasedBench
{
    public static bool $ready = false;

    public function setUp(): void
    {
        self::$ready = true;
    }

    public function bench(): void
    {
    }
}
