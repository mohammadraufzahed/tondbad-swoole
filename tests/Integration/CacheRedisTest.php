<?php

declare(strict_types=1);

use TondbadSwoole\Tests\Support\RedisContainer;

beforeAll(function () {
    if (!RedisContainer::enabled()) {
        return;
    }

    RedisContainer::start();

    $config = RedisContainer::config();
    putenv('REDIS_HOST=' . $config['host']);
    putenv('REDIS_PORT=' . $config['port']);
});

afterAll(function () {
    RedisContainer::stop();
});

it('runs getOrSet concurrently with Redis as L2 and loads the value once', function () {
    if (!RedisContainer::enabled()) {
        test()->markTestSkipped('Redis integration tests disabled; set RUN_INTEGRATION_TESTS=1 and ensure Predis/Redis is available.');

        return;
    }

    $script = __DIR__ . '/../Support/CacheConcurrencyScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $result = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($result)->toHaveKey('results');
    expect($result['results'])->toHaveCount(10);
    expect($result['results'])->each->toBe('computed');
    expect($result['callbackCount'])->toBe(1);
    expect($result['loadCount'])->toBe(1);
    expect($result['hitCount'])->toBe(9);
    expect($result['missCount'])->toBe(1);
});

it('invalidates tagged entries across L1 and L2 with Redis', function () {
    if (!RedisContainer::enabled()) {
        test()->markTestSkipped('Redis integration tests disabled; set RUN_INTEGRATION_TESTS=1 and ensure Predis/Redis is available.');

        return;
    }

    $script = __DIR__ . '/../Support/CacheRedisTagsScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $result = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($result['usersBefore'])->toBe('ava');
    expect($result['ordersBefore'])->toBe('order-data');
    expect($result['usersAfter'])->toBeNull();
    expect($result['ordersAfter'])->toBe('order-data');
});
