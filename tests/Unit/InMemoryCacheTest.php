<?php

declare(strict_types=1);

use TondbadSwoole\Core\Cache\InMemoryCache;

it('sets and gets a value', function () {
    $cache = new InMemoryCache(1024, 3600000);

    expect($cache->set('key', 'value'))->toBeTrue();
    expect($cache->get('key'))->toBe('value');
});

it('returns null for a missing key', function () {
    $cache = new InMemoryCache(1024, 3600000);

    expect($cache->get('missing'))->toBeNull();
});

it('reports an existing key through has()', function () {
    $cache = new InMemoryCache(1024, 3600000);
    $cache->set('key', 'value');

    expect($cache->has('key'))->toBeTrue();
});

it('removes a key through delete()', function () {
    $cache = new InMemoryCache(1024, 3600000);
    $cache->set('key', 'value');

    expect($cache->delete('key'))->toBeTrue();
    expect($cache->get('key'))->toBeNull();
    expect($cache->has('key'))->toBeFalse();
});

it('expires an item after ttl', function () {
    $cache = new InMemoryCache(1024, 3600000);

    $cache->set('key', 'value', 1);
    expect($cache->has('key'))->toBeTrue();

    sleep(2);

    expect($cache->get('key'))->toBeNull();
    expect($cache->has('key'))->toBeFalse();
});

it('clears all keys', function () {
    $cache = new InMemoryCache(1024, 3600000);

    $cache->set('a', 1);
    $cache->set('b', 2);

    expect($cache->clear())->toBeTrue();

    expect($cache->get('a'))->toBeNull();
    expect($cache->get('b'))->toBeNull();
});

it('sets and gets multiple items', function () {
    $cache = new InMemoryCache(1024, 3600000);

    expect($cache->setMultiple(['a' => 1, 'b' => 2]))->toBeTrue();
    expect($cache->getMultiple(['a', 'b']))->toBe(['a' => 1, 'b' => 2]);
});
