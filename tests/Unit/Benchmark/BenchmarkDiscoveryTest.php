<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\BenchmarkDiscovery;
use TondbadSwoole\Benchmark\Scenario;

it('discovers scenarios from the benchmarks directory', function () {
    $discovery = new BenchmarkDiscovery([__DIR__ . '/../../../benchmarks']);
    $scenarios = $discovery->discover();

    expect($scenarios)->toBeArray();
    expect($scenarios)->not->toBeEmpty();

    $names = array_map(fn (Scenario $s) => $s->name, $scenarios);

    expect($names)->toContain('EventDispatcherBenchmark::benchDispatch');
    expect($names)->toContain('ValidationBenchmark::benchSchema');
    expect($names)->toContain('ValidationBenchmark::benchValidator');
});
