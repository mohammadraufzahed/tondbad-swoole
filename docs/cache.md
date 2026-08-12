# Cache

Tondbād supports multiple cache drivers through a single `CacheInterface`.

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
        'password' => $env->get('redis.password'),
        'database' => $env->get('redis.database', 0),
        'timeout' => $env->get('redis.timeout', 5.0),

        'options' => [
            'prefix' => $env->get('redis.options.prefix', 'tondbad:'),
        ],
    ],
];
```

Set `CACHE_STORE` to `in-memory`, `phpredis`, or `predis`. Environment variables become `CACHE_DEFAULT`, `REDIS_HOST`, etc.

## Using the cache

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

## Cache forever

```php
$cache->set('config', $configValue); // no TTL
```

## Multiple operations

```php
$cache->setMultiple([
    'user.1' => $user1,
    'user.2' => $user2,
], 3600);

$users = $cache->getMultiple(['user.1', 'user.2']);
$cache->deleteMultiple(['user.1', 'user.2']);
```

## Remember pattern

```php
function remember(string $key, int $ttl, callable $callback): mixed
{
    $cache = cache();

    if ($cache->has($key)) {
        return $cache->get($key);
    }

    $value = $callback();
    $cache->set($key, $value, $ttl);

    return $value;
}
```

## Drivers

- **in-memory** — in-process PSR-16 compatible store with TTL support. Best for single-worker or testing.
- **phpredis** — uses the `ext-redis` `Redis` class.
- **predis** — uses `predis/predis` for environments without `ext-redis`.

## Serialization

Values are serialized with Symfony Serializer using JSON encoding. Objects are encoded/decoded automatically.
