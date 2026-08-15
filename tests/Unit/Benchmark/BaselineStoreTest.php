<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\BaselineStore;
use TondbadSwoole\Benchmark\BenchmarkResult;
use TondbadSwoole\Benchmark\Mode;
use TondbadSwoole\Benchmark\TimeUnit;

it('saves and loads a baseline', function () {
    $dir = sys_get_temp_dir() . '/tondbad-benchmark-' . uniqid();
    $store = new BaselineStore($dir);

    $result = new BenchmarkResult(
        name: 'demo',
        group: '',
        mode: Mode::AverageTime,
        timeUnit: TimeUnit::Microseconds,
        iterations: 10,
        invocations: 1,
        forks: 1,
        min: 100.0,
        max: 500.0,
        mean: 300.0,
        median: 300.0,
        stddev: 100.0,
        p95: 500.0,
        ci95Lower: 250.0,
        ci95Upper: 350.0,
        opsPerSecond: 3333.33,
        memoryPerOp: 0.0,
        outliers: 0,
    );

    $store->save('main.json', [$result]);
    $loaded = $store->load('main.json');

    expect($loaded)->toHaveKey('demo');
    expect($loaded['demo']->name)->toBe('demo');
    expect($loaded['demo']->mean)->toBe(300.0);

    // Cleanup
    @unlink($dir . '/main.json');
    @rmdir($dir);
});
