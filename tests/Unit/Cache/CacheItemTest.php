<?php

declare(strict_types=1);

use TondbadSwoole\Contracts\Cache\CacheItem;

it('sets lifetime and refresh ratio fluently', function () {
    $item = new CacheItem('key');

    expect($item->lifetime(60, 0.5))->toBe($item);
    expect($item->lifetime)->toBe(60);
    expect($item->refreshRatio)->toBe(0.5);
    expect($item->expiresAt())->toBeGreaterThan(time());
    expect($item->refreshAt())->toBeGreaterThan(time());
});

it('sets tags fluently', function () {
    $item = new CacheItem('key');

    $item->tag('users', 'orders');

    expect($item->tags)->toBe(['users', 'orders']);
});

it('sets weight fluently', function () {
    $item = new CacheItem('key');

    $item->weight(10);

    expect($item->weight)->toBe(10);
});

it('uses zero lifetime for no expiration', function () {
    $item = new CacheItem('key');

    expect($item->expiresAt())->toBe(0);
    expect($item->refreshAt())->toBe(0);
});
