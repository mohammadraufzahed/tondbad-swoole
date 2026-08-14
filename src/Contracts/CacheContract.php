<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts;

use TondbadSwoole\Contracts\Cache\CacheItem;
use TondbadSwoole\Contracts\Cache\CacheStats;

interface CacheContract extends CacheInterface
{
    /**
     * @param callable(CacheItem): mixed $callback
     */
    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed;

    /**
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): bool;

    public function refresh(string $key): bool;

    public function stats(): CacheStats;
}
