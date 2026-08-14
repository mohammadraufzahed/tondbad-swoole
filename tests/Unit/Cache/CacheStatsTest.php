<?php

declare(strict_types=1);

use TondbadSwoole\Contracts\Cache\CacheStats;

it('tracks hits and misses', function () {
    $stats = new CacheStats();

    $stats->recordHit(true);
    $stats->recordHit(false);
    $stats->recordMiss();

    expect($stats->hitCount)->toBe(2);
    expect($stats->l1HitCount)->toBe(1);
    expect($stats->l2HitCount)->toBe(1);
    expect($stats->missCount)->toBe(1);
    expect($stats->hitRate())->toBe(0.6667);
});

it('tracks loads and failures', function () {
    $stats = new CacheStats();

    $stats->recordLoad(0.05);
    $stats->recordLoad(0.10, false);

    expect($stats->loadCount)->toBe(2);
    expect($stats->loadFailureCount)->toBe(1);
    expect($stats->loadTime)->toBeGreaterThan(0.149);
    expect($stats->loadTime)->toBeLessThan(0.151);
});

it('tracks refreshes and evictions', function () {
    $stats = new CacheStats();

    $stats->recordRefresh();
    $stats->recordRefresh(false);
    $stats->recordEviction(5);

    expect($stats->refreshCount)->toBe(2);
    expect($stats->refreshFailureCount)->toBe(1);
    expect($stats->evictionCount)->toBe(1);
    expect($stats->evictionWeight)->toBe(5);
});

it('returns zero rates when no requests', function () {
    $stats = new CacheStats();

    expect($stats->hitRate())->toBe(0.0);
    expect($stats->l1HitRate())->toBe(0.0);
    expect($stats->l2HitRate())->toBe(0.0);
});
