# Cache

Tondbād uses a single `Cache` facade backed by one `HybridStore` pipeline. The public API blends Laravel-style `cache()` helpers, Symfony-style `getOrSet` callbacks, and Caffeine/ASP.NET-inspired L1/L2 coordination, tag invalidation, refresh-ahead, and stampede protection.

## Configuration

`config/cache.php`:

```php
<?php

declare(strict_types=1);

return [
    'default' => $env->get('cache.default', 'in-memory'),

    'in_memory' => [
        'size' => (int) $env->get('cache.in_memory.size', 1024),
        'clean_interval' => (int) $env->get('cache.in_memory.clean_interval', 1000),
    ],

    'redis' => [
        'scheme' => $env->get('redis.scheme', 'tcp'),
        'host' => $env->get('redis.host', '127.0.0.1'),
        'port' => $env->get('redis.port', 6379),
        'password' => $env->get('redis.password', null),
        'database' => $env->get('redis.database', 0),
        'timeout' => $env->get('redis.timeout', 5.0),

        'pool' => [
            'size' => (int) $env->get('redis.pool.size', 4),
        ],

        'options' => [
            'prefix' => $env->get('redis.options.prefix', 'tondbad:'),
        ],
    ],
];
```

Set `CACHE_DEFAULT` to `in-memory`, `redis`, `predis`, or `phpredis`.

- `in-memory` keeps L1 in an `OpenSwoole\Table` per worker.
- `redis`/`predis` adds an L2 Redis layer behind the same L1 table.
- `phpredis` uses the `ext-redis` client.

## Basic PSR-16 usage

```php
$cache = cache();

$cache->set('user.1', ['name' => 'Ava'], 3600);
$user = $cache->get('user.1');

if ($cache->has('user.1')) {
    // ...
}

$cache->delete('user.1');
$cache->clear();
```

## Cache-aside with `getOrSet`

`getOrSet` is the primary entry point. The callback receives a `CacheItem` to declare lifetime, tags, weight, and metadata.

```php
$stats = cache()->getOrSet('dashboard:stats', function (CacheItem $item) {
    $item->lifetime(60, refreshRatio: 0.5);
    $item->tag('users', 'orders');
    $item->weight(10);

    return computeDashboard();
});
```

- `lifetime(int $seconds, ?float $refreshRatio = null)` — sets expiry and optional refresh window.
- `tag(string ...$tags)` — associates the entry with tags for invalidation.
- `weight(int $weight)` — hint for future L1 eviction policies.

## Tag invalidation

Invalidate every entry carrying one or more tags:

```php
cache()->invalidateTags(['users']);
```

The tag manager bumps a global version for each tag. Stale entries are detected on read and reloaded, so invalidation is safe across L1/L2 and across worker processes when Redis is the L2.

## Refresh-ahead

A `refreshRatio` tells the store to recompute the value after that portion of the lifetime has elapsed. The next `getOrSet` after the refresh point reloads the value before it expires, keeping the cache warm.

```php
cache()->getOrSet('heavy-report', function (CacheItem $item) {
    $item->lifetime(120, refreshRatio: 0.75);

    return buildReport();
});
```

## Cache statistics

```php
$stats = cache()->stats();

echo $stats->hitRate();
echo $stats->l1HitRate();
echo $stats->l2HitRate();
```

The CLI can print them:

```bash
./tondbad cache:status
```

## Console commands

- `cache:clear` — clear data cache and compiled framework/route caches.
- `cache:forget-tags users orders` — invalidate all entries tagged `users` or `orders`.
- `cache:status` — print current hit/miss/load statistics.

## Architecture

The pipeline is:

```
PSR-16 / CacheContract API
        │
        ▼
   HybridStore
        │
   ┌────┴────┐
   ▼         ▼
  L1        L2
OpenSwoole  Redis/Files
  Table
```

- `HybridStore` coordinates L1, L2, serialization, stats, lock/tag/refresh policies.
- `CacheEntry` is the internal serialized blob stored in L1/L2.
- `JsonSerializer` is the default value serializer; custom serializers implement `TondbadSwoole\Core\Cache\Serializer`.
- `ChannelLock` prevents stampede loads inside a single worker under OpenSwoole coroutines.
- `RedisCache` uses a coroutine-safe `Predis` connection pool; `RedisTagManager` makes tag invalidation work across workers.
