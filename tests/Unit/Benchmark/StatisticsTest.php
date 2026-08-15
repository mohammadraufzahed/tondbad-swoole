<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\Statistics;

it('computes basic statistics', function () {
    $samples = [100.0, 200.0, 300.0, 400.0, 500.0];
    $stats = Statistics::analyze($samples);

    expect($stats['min'])->toBe(100.0);
    expect($stats['max'])->toBe(500.0);
    expect($stats['mean'])->toBe(300.0);
    expect($stats['median'])->toBe(300.0);
    expect($stats['opsPerSecond'])->toBeGreaterThan(0.0);
});

it('returns zero for empty samples', function () {
    $stats = Statistics::analyze([]);

    expect($stats['mean'])->toBe(0.0);
    expect($stats['stddev'])->toBe(0.0);
    expect($stats['opsPerSecond'])->toBe(0.0);
    expect($stats['outliers'])->toBe(0);
});

it('detects outliers', function () {
    $samples = [1.0, 1.1, 1.2, 1.3, 100.0];
    $stats = Statistics::analyze($samples);

    expect($stats['outliers'])->toBe(1);
});
