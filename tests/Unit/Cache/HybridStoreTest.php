<?php

declare(strict_types=1);

use TondbadSwoole\Contracts\Cache\CacheItem;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Cache\InMemoryTagManager;
use TondbadSwoole\Core\Cache\JsonSerializer;

function createHybridStore(): HybridStore
{
    return new HybridStore(
        new InMemoryCache(128, 0),
        new InMemoryCache(128, 0),
        new JsonSerializer(),
        new InMemoryTagManager(),
    );
}

it('stores and retrieves a value', function () {
    $cache = createHybridStore();

    $cache->set('name', 'tondbad');

    expect($cache->get('name'))->toBe('tondbad');
    expect($cache->has('name'))->toBeTrue();
});

it('returns the default for a missing key', function () {
    $cache = createHybridStore();

    expect($cache->get('missing', 'fallback'))->toBe('fallback');
    expect($cache->has('missing'))->toBeFalse();
});

it('computes a value with getOrSet', function () {
    $cache = createHybridStore();

    $value = $cache->getOrSet('greeting', function (CacheItem $item) {
        $item->lifetime(60);

        return 'hello';
    });

    expect($value)->toBe('hello');
    expect($cache->get('greeting'))->toBe('hello');
});

it('backfills the l1 cache from l2', function () {
    $l1 = new InMemoryCache(128, 0);
    $l2 = new InMemoryCache(128, 0);
    $cache = new HybridStore($l1, $l2, new JsonSerializer(), new InMemoryTagManager());

    $cache->set('shared', 'value', 60);

    $l1->clear();

    expect($l1->get('shared'))->toBeNull();
    expect($cache->get('shared'))->toBe('value');
    expect($l1->get('shared'))->not->toBeNull();
});

it('expires a value after ttl', function () {
    $cache = createHybridStore();

    $cache->set('expires', 'value', 1);

    expect($cache->get('expires'))->toBe('value');

    sleep(2);

    expect($cache->get('expires'))->toBeNull();
});

it('invalidates entries by tag', function () {
    $cache = createHybridStore();

    $cache->getOrSet('users:1', function (CacheItem $item) {
        $item->lifetime(60)->tag('users');

        return 'user-one';
    });

    $cache->getOrSet('orders:2', function (CacheItem $item) {
        $item->lifetime(60)->tag('orders');

        return 'order-two';
    });

    expect($cache->get('users:1'))->toBe('user-one');
    expect($cache->get('orders:2'))->toBe('order-two');

    $cache->invalidateTags(['users']);

    expect($cache->get('users:1'))->toBeNull();
    expect($cache->get('orders:2'))->toBe('order-two');
});

it('refreshes an expired value on getOrSet', function () {
    $cache = createHybridStore();
    $calls = 0;

    $value = $cache->getOrSet('refreshable', function (CacheItem $item) use (&$calls) {
        $item->lifetime(1, refreshRatio: 0.5);

        $calls++;

        return 'v' . $calls;
    });

    expect($value)->toBe('v1');
    expect($calls)->toBe(1);

    sleep(2);

    $value = $cache->getOrSet('refreshable', function (CacheItem $item) use (&$calls) {
        $item->lifetime(1, refreshRatio: 0.5);

        $calls++;

        return 'v' . $calls;
    });

    expect($value)->toBe('v2');
    expect($calls)->toBe(2);
});

it('records cache statistics', function () {
    $cache = createHybridStore();

    $cache->getOrSet('stats-key', function (CacheItem $item) {
        $item->lifetime(60);

        return 'computed';
    });

    $cache->get('stats-key');
    $cache->get('missing');

    $stats = $cache->stats();

    expect($stats->hitCount)->toBe(1);
    expect($stats->missCount)->toBe(2);
    expect($stats->loadCount)->toBe(1);
    expect($stats->hitRate())->toBe(0.3333);
});
