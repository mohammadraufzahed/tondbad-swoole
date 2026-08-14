# Cohesive next-generation caching for Tondbād Swoole

This proposal builds one cache pipeline for Tondbād Swoole. It takes the best parts of **Caffeine** (smart in-process caching), **ASP.NET HybridCache** (L1/L2 coordination with stampede protection), and **Symfony Cache Contracts** (`getOrSet` callback API) and shapes them into a single `Cache` facade.

There is no feature duplication. There is one public contract, one read path, and one policy surface.

---

## 1. One-sentence design

> `Cache` is a coroutine-safe, hybrid L1/L2 cache with a single `getOrSet($key, $callback)` entry point. Every other concern — TTL, refresh, tags, stats, locks — is configured through the `CacheItem` passed to that callback.

---

## 2. The only public API

```php
$value = cache()->get('users:1');              // read only
cache()->delete('users:1');                    // remove one key
cache()->clear();                              // clear all

// the one write/refresh path
cache()->getOrSet('dashboard:stats', function (CacheItem $item) {
    $item->lifetime(60, refreshRatio: 0.5);    // expire in 60s, refresh when 30s old
    $item->tag('users', 'orders');             // invalidate by tag later
    $item->weight(10);                          // eviction priority (optional)

    return computeDashboard();
});

// invalidate everything that declared these tags
cache()->invalidateTags(['users']);

// manual background refresh of one key
cache()->refresh('dashboard:stats');

// runtime telemetry
echo cache()->stats()->hitRate();
```

No other classes are exposed for normal usage. `HybridStore`, `InMemoryCache`, `RedisCache`, `LockManager`, `RefreshScheduler`, `CacheStats` are internal policies of this one API.

---

## 3. How external ideas are blended, not copied

| External idea | What we keep | Where it lives |
|---|---|---|
| **Caffeine** | Bounded L1, frequency/recency eviction, refresh-ahead while serving old value, per-entry lifetime, cache stats. | `InMemoryCache` L1 layer and `CacheStats`. |
| **ASP.NET HybridCache** | L1 local + L2 shared read path, one loader per key during stampede, tag invalidation across nodes. | `HybridStore` orchestration and `LockManager`. |
| **Symfony Cache Contracts** | `getOrSet($key, $callback)` as the single entry point; callback declares TTL/tags/weight on `CacheItem`. | `Cache` facade and `CacheItem`. |

---

## 4. Unified read/write pipeline

### 4.1 `getOrSet` — the only path that produces values

```
getOrSet(key, callback)
│
├─ 1. L1 lookup (OpenSwoole\Table)
│   ├─ valid and not stale → stats.hit++, return value
│   ├─ within refresh window → stats.hit++, schedule background refresh, return current value
│   └─ miss or expired → continue
│
├─ 2. L2 lookup (Redis / filesystem)
│   ├─ valid → backfill L1, return value
│   └─ miss → continue
│
├─ 3. Stampede protection
│   ├─ acquire per-key lock (OpenSwoole\Lock inside worker, SET NX across workers)
│   ├─ acquired → run callback, serialize, store in L2 then L1, release lock
│   └─ not acquired → wait for lock or return a stale value if allowed
│
└─ 4. Return value
```

### 4.2 Lifetime policy: one concept, one method

`CacheItem::lifetime(int $seconds, ?float $refreshRatio = null)` replaces both `expiresAfter` and `refreshAfter`.

- `$seconds` = total time the value is considered fresh.
- `$refreshRatio` = optional fraction of `$seconds` after which a read may trigger a background refresh.
  - `0.5` with `lifetime(60)` means: serve the value up to 60s, but start refreshing it in background after 30s.
  - omitted → no proactive refresh; the value simply expires at 60s.

This removes the duplicate "expires vs. refresh" confusion and still gives Caffeine-style refresh-ahead and Symfony-style TTL in one call.

### 4.3 Tag invalidation

Tags are stored inside the `CacheItem` metadata and recorded with the cached value:

```php
$item->tag('users', 'orders');
```

Invalidation bumps a per-tag generation counter in L2 and removes affected entries from L1. Subsequent L1 reads compare the item's recorded tag generation with the current generation and treat older versions as stale, forcing a re-check of L2 or a reload.

```php
cache()->invalidateTags(['users']);
```

### 4.4 Eviction policy

L1 (`OpenSwoole\Table`) is bounded. When it fills, entries are evicted by **weight-adjusted frequency + recency** (W-TinyLFU) or by plain weight if `weight()` is used. Expired entries are cleaned lazily on read and periodically by `OpenSwoole\Timer`.

### 4.5 Statistics

`CacheStats` is collected at each pipeline step:

```
- hitCount / missCount
- l1HitCount / l2HitCount
- loadCount / loadFailureCount / loadTime
- refreshCount / refreshFailureCount
- evictionCount / evictionWeight
- hitRate()
```

---

## 5. Internal architecture

```
┌─────────────────────────────────────────────┐
│               Cache facade                   │
│   get / delete / clear / getOrSet / refresh  │
│        invalidateTags / stats                 │
└─────────────────────┬───────────────────────┘
                      │
        ┌─────────────▼──────────────┐
        │        HybridStore         │
        │  L1 + L2 + locks + stats   │
        └──────┬──────────────┬──────┘
               │              │
    ┌──────────▼───┐   ┌──────▼──────┐
    │ L1 Store     │   │ L2 Store    │
    │ OpenSwoole   │   │ Redis or    │
    │   Table      │   │ Filesystem  │
    └──────────────┘   └─────────────┘
```

- `CacheItem` is a value object passed to the callback; it carries lifetime, tags, weight, and metadata.
- `HybridStore` runs the pipeline. It owns L1, L2, `LockManager`, `RefreshScheduler`, `Serializer`, and `CacheStats`.
- `LockManager` chooses an in-process lock (`OpenSwoole\Lock`) when L2 is not shared, and Redis `SET NX` when L2 is Redis, so stampede protection works on a single worker or across a cluster.
- `RefreshScheduler` uses `OpenSwoole\Timer::after` to trigger background refreshes. The callback metadata is stored alongside the value so the refresh can re-run the original loader.

---

## 6. Configuration

```php
// config/cache.php
return [
    'default' => $env->get('cache.default', 'hybrid'),

    'hybrid' => [
        'l1' => 'in-memory',
        'l2' => 'redis',
        'lock_wait_ms' => 2000,
    ],

    'in_memory' => [
        'size' => 4096,
        'eviction_policy' => 'wtinylfu', // wtinylfu | lru | lfu
        'clean_interval' => 1000,        // ms
    ],

    'redis' => [
        'client' => 'predis', // predis | phpredis | swoole-pool
        'pool' => ['size' => 16, 'max_idle_time' => 60],
        // host, port, password, database, prefix …
    ],

    'file' => [
        'path' => $basePath . '/storage/framework/cache',
    ],

    'serializer' => 'json', // json | php | igbinary
];
```

---

## 7. Implementation phases

### Phase A — `CacheItem` + `Cache` facade + L1 rewrite

1. Add `CacheItem`, `CacheStats`, `CacheContract`.
2. Add `Cache` facade and `cache()->getOrSet(...)`.
3. Refactor `InMemoryCache` into the L1 store with per-entry lifetime, tag metadata, and W-TinyLFU/LRU eviction.
4. Keep PSR-16 `CacheInterface` as the low-level surface.

### Phase B — Hybrid L1/L2 + stampede protection

1. Build `HybridStore`.
2. Add unified `RedisCache` (merges `PredisCache` + `PhpRedisCache`) and `FilesystemCache` as L2.
3. Implement the full read path: L1 → L2 → lock → callback → backfill both.
4. Add `LockManager` (in-process and Redis `SET NX`).

### Phase C — Tags and invalidation

1. Store per-tag generation numbers in L2.
2. Implement `invalidateTags`.
3. Make L1 reads validate tag generations.
4. Add `cache:clear` and `cache:forget-tags` CLI.

### Phase D — Refresh + stats

1. Implement `CacheItem::lifetime($ttl, refreshRatio: ...)`.
2. Add `RefreshScheduler` with `OpenSwoole\Timer` and callback persistence.
3. Collect `CacheStats` and expose `cache()->stats()` + `cache:status` CLI.

### Phase E — Coroutine-safe Redis and pools

1. Add `SwooleRedisPool` option or verify `HOOK_TCP` with Predis/PhpRedis.
2. Add pluggable serializers (JSON, PHP, igbinary).
3. Stress-test concurrency.

### Phase F — Documentation and E2E

1. Rewrite `docs/cache.md` around the unified API.
2. Add tests for stampede, L1/L2 consistency, tags, refresh, and stats.
3. Run `composer test` and end-to-end verification.

---

## 8. Why this is the right shape

- **One entry point** (`getOrSet`) removes the confusion of `set` vs. `get` vs. `remember`.
- **One lifetime method** (`lifetime`) replaces separate `expiresAfter` / `refreshAfter` knobs.
- **One invalidation model** (tags) works for both L1 and L2.
- **One stats object** tells the whole story.
- The framework stays OpenSwoole-native (`OpenSwoole\Table`, `OpenSwoole\Timer`, `HOOK_TCP`) while borrowing the best semantics from Caffeine, HybridCache, and Symfony.
