# Implementation plan: unified `Cache` pipeline for Tondbād Swoole

This plan implements the cohesive caching proposal in `docs/caching-proposal.md`. It is broken into six phases that build one public `Cache` API and one `HybridStore` pipeline.

**Estimated total: 1.5–2 Devin sessions** (most work can happen in the current session; Redis pool tuning and E2E stress may need a short follow-up).

---

## 0. Goals and success criteria

- `cache()->getOrSet($key, $callback, $ttl)` works as the single cache-aside entry point.
- `CacheItem` carries lifetime, tags, and weight inside the callback.
- L1 (`OpenSwoole\Table`) + L2 (Redis/filesystem) read path backfills correctly.
- Stampede protection prevents multiple concurrent loaders for the same key.
- `cache()->invalidateTags([...])` works across workers via Redis-backed tag generations.
- `cache()->refresh($key)` and `lifetime(..., refreshRatio: ...)` trigger background reloads.
- `cache()->stats()` returns hit/miss/L1/L2/load/refresh/eviction metrics.
- `composer test` passes and full E2E cache verification passes.

---

## Phase A — Core contract and L1 rewrite

**Goal**: define the public API and rebuild `InMemoryCache` as the L1 store.

### Files to create

- `src/Contracts/CacheContract.php` — extends `CacheInterface` with `getOrSet`, `invalidateTags`, `refresh`, `stats`.
- `src/Core/Cache/CacheItem.php` — value object: `key`, `value`, `lifetime`, `refreshRatio`, `tags`, `weight`, `metadata`.
- `src/Core/Cache/CacheStats.php` — immutable stats object with `hitRate()`, `l1HitRate()`, etc.
- `src/Core/Cache/Cache.php` — facade implementing `CacheContract` and PSR-16.
- `src/Facades/Cache.php` (optional static facade) — `Cache::getOrSet(...)`.

### Files to modify

- `src/Contracts/CacheInterface.php` — keep PSR-16 as-is.
- `src/Core/Cache/InMemoryCache.php` — rewrite around `OpenSwoole\Table`:
  - columns: `value` (string), `expires_at` (int), `created_at` (int), `access_count` (int), `last_access` (int), `tags` (string), `weight` (int).
  - per-entry TTL, weighted/LRU/LFU eviction, background clean timer.
- `src/Providers/Default/CacheServiceProvider.php` — bind `Cache` singleton.
- `src/Support/helpers.php` — `cache()` returns the `Cache` facade.
- `config/cache.php` — add `in_memory` options (`size`, `eviction_policy`, `clean_interval`) and `hybrid` config.

### Tests

- `tests/Unit/Cache/CacheItemTest.php`
- `tests/Unit/Cache/CacheStatsTest.php`
- `tests/Unit/Cache/InMemoryCacheTest.php` (TTL, eviction, weight, stats)
- `tests/Unit/Cache/CacheFacadeTest.php`

**Estimate**: 0.3 session.

---

## Phase B — Hybrid L1/L2 + stampede protection

**Goal**: make `Cache` actually coordinate L1 and L2 with a single loader per key.

### Files to create

- `src/Core/Cache/HybridStore.php` — the pipeline. Contains L1, L2, `LockManager`, `Serializer`, `RefreshScheduler`, `CacheStats`.
- `src/Core/Cache/LockManager.php` — per-key lock abstraction.
  - `OpenSwoole\Lock` for in-process / single-node.
  - Redis `SET key NX EX` for cross-node when Redis is configured.
- `src/Core/Cache/RedisCache.php` — unified Redis adapter.
  - Selects driver: `predis`, `phpredis`, or `swoole-pool` based on `cache.redis.client`.
  - Implements `CacheInterface`.
  - Supports pipeline `mget`/`mset`, `SET NX` for locks, Lua script for tag generation.
- `src/Core/Cache/FilesystemCache.php` — L2 for tests and single-node deployments.
- `src/Core/Cache/Serializer.php` + `src/Core/Cache/Serializers/{Json,Php,Igbinary}Serializer.php`.

### Files to modify

- `src/Providers/Default/CacheServiceProvider.php` — wire `HybridStore` and `RedisCache`.
- `src/Providers/Default/PredisCacheProvider.php` — deprecate or redirect to `RedisCache`.
- `src/Providers/Default/PhpRedisCacheProvider.php` — deprecate or redirect.
- `src/Core/Cache/PredisCache.php` / `PhpRedisCache.php` — keep as BC aliases or remove.

### Tests

- `tests/Unit/Cache/HybridStoreTest.php` (L1 hit, L2 hit, miss, backfill)
- `tests/Unit/Cache/LockManagerTest.php`
- `tests/Unit/Cache/RedisCacheTest.php` (uses Testcontainers Redis if available)
- `tests/Unit/Cache/FilesystemCacheTest.php`
- `tests/Unit/Cache/SerializerTest.php`

**Estimate**: 0.5 session.

---

## Phase C — Tags and invalidation

**Goal**: tag-based invalidation works across L1 and L2.

### Files to create

- `src/Core/Cache/TagManager.php` — stores per-tag generation counters.
  - For in-memory-only: `OpenSwoole\Table` or `OpenSwoole\Atomic`/`Lock`.
  - For Redis-backed: store `__tags:users:gen` as a Redis integer and increment.
- `src/Console/Commands/CacheClearCommand.php` — `cache:clear`.
- `src/Console/Commands/CacheForgetTagsCommand.php` — `cache:forget-tags tag1 tag2`.

### Files to modify

- `src/Core/Cache/CacheItem.php` — add tags to metadata.
- `src/Core/Cache/InMemoryCache.php` — store tags and validate on read.
- `src/Core/Cache/HybridStore.php` — implement `invalidateTags()`; on read compare tag generations.
- `src/Core/Cache/RedisCache.php` — support tag generation `INCR` and Lua invalidation.
- `config/console.php` or `config/app.php` — register new commands.

### Tests

- `tests/Unit/Cache/TagManagerTest.php`
- `tests/Unit/Cache/HybridStoreTagsTest.php`
- `tests/Feature/CacheForgetTagsCommandTest.php`

**Estimate**: 0.3 session.

---

## Phase D — Refresh-ahead and statistics

**Goal**: `lifetime(..., refreshRatio: ...)` and `cache()->refresh($key)` trigger background reloads; stats are complete.

### Files to create

- `src/Core/Cache/RefreshScheduler.php` — schedules per-key refreshes.
  - Uses `OpenSwoole\Timer::after` for one-shot background refresh.
  - Stores `CacheItem` + callback metadata so refresh can re-run the loader.
- `src/Console/Commands/CacheStatusCommand.php` — `cache:status` prints `CacheStats`.

### Files to modify

- `src/Core/Cache/CacheItem.php` — add `refreshRatio`, `callback` metadata.
- `src/Core/Cache/InMemoryCache.php` — on read, detect refresh window and notify `RefreshScheduler`.
- `src/Core/Cache/HybridStore.php` — implement `refresh()`; collect `CacheStats`.
- `src/Core/Cache/CacheStats.php` — add refresh metrics.
- `src/Support/helpers.php` — ensure `cache()->refresh(...)` accessible.

### Tests

- `tests/Unit/Cache/RefreshSchedulerTest.php`
- `tests/Unit/Cache/CacheStatsTest.php` (refresh, load failure counts)
- `tests/Feature/CacheRefreshTest.php`
- `tests/Feature/CacheStatusCommandTest.php`

**Estimate**: 0.3 session.

---

## Phase E — Coroutine-safe Redis and pools

**Goal**: Redis L2 is safe under OpenSwoole concurrency and supports connection pooling.

### Files to create

- `src/Core/Cache/RedisPool.php` — optional pool using `OpenSwoole\Coroutine\Pool` or `OpenSwoole\Runtime::HOOK_TCP` + Predis.
- `src/Core/Cache/Serializers/IgbinarySerializer.php` — only if `igbinary` extension present.

### Files to modify

- `src/Core/Cache/RedisCache.php` — accept `RedisPool` or `ClientInterface`; add `HOOK_TCP` note in docs.
- `config/cache.php` — add `pool.size`, `pool.max_idle_time`, `serializer`.
- `composer.json` — suggest `ext-igbinary` and `openswoole/core` if needed.

### Tests

- `tests/Unit/Cache/RedisPoolTest.php` (spawn multiple coroutines, assert no duplicate loads via stampede)
- `tests/Stress/CacheStampedeTest.php` (100 concurrent `getOrSet` calls, assert callback runs once per key)

**Estimate**: 0.3–0.5 session (most is testing under concurrency).

---

## Phase F — Documentation and end-to-end verification

**Goal**: users know the new API; the framework passes tests and runtime E2E.

### Files to modify/create

- `docs/cache.md` — rewrite around the unified `Cache` API with examples.
- `docs/caching-proposal.md` / `docs/caching-plan.md` — mark as implemented or move to `docs/archive/`.
- `tests/E2E/CacheE2ETest.php` or expand `tests/Integration/CacheIntegrationTest.php`:
  - boot a temporary app with `InMemoryCache` L1 + `FilesystemCache` L2
  - verify `getOrSet`, tag invalidation, refresh, stats
- `tests/Architecture/CacheArchitectureTest.php` — ensure `Cache` facade does not leak into low-level adapters.

### Final verification

- `php -l` on all new/changed PHP files.
- `composer validate --strict`.
- `composer test` passes.
- E2E pass: `php bin/tondbad serve` + `curl` endpoints that exercise cache read/refresh/invalidate.

**Estimate**: 0.2–0.3 session.

---

## Dependencies and ordering

```
Phase A (core API + L1)
    │
    ▼
Phase B (Hybrid L1/L2 + locks)
    │
    ├──▶ Phase C (tags)
    │
    ├──▶ Phase D (refresh + stats)
    │
    └──▶ Phase E (Redis pools / serializers)
              │
              ▼
         Phase F (docs + E2E)
```

Phases C and D can be worked in parallel once B is green. Phase E can start once the `RedisCache` adapter from B exists.

---

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| `OpenSwoole\Table` fixed column size (65 KB) truncates large values. | Keep L1 for small hot values; L2 for larger payloads; add `max_value_size` warning. |
| L1 is per-worker if `Table` is created inside a worker; cross-worker L1 invalidation does not exist. | Use Redis tag generations as the source of truth; L1 self-invalidates on read. This matches HybridCache semantics. |
| `OpenSwoole\Lock` is in-process only. | `LockManager` falls back to Redis `SET NX` when Redis is the L2. |
| Tag generation counters in Redis become hot keys. | Cache tag generation versions in L1 with a short TTL; only re-fetch when stale. |
| Callbacks cannot be serialized and restored for refresh. | Store `CacheItem` metadata and require the application to register named loaders, or design `getOrSet` to accept a `CacheItem` factory for refresh. Alternative: refresh is manual or uses a `refresh` closure passed explicitly. |
| Igbinary serializer may not be installed. | Make `Json` the default; `Php` and `Igbinary` are opt-in. |

---

## OpenSwoole specifics

- L1 uses `OpenSwoole\Table` and `OpenSwoole\Timer` for cleanup.
- L2 Redis I/O must run inside the `OpenSwoole\Runtime::HOOK_TCP` context (already enabled in `App::run`).
- Refresh runs in a coroutine via `OpenSwoole\Timer::after`; the callback must be coroutine-safe and release DB connections after itself.
- `cache()` helper must return the same `Cache` instance within a worker; `CacheServiceProvider` binds it as a singleton.

---

## Recommended next step

Begin with **Phase A**. Once `CacheItem`, `Cache`, and the new `InMemoryCache` L1 are green, move directly to **Phase B** to wire `HybridStore` and a `FilesystemCache` L2. Redis and pools can be added in **Phase E** once the core pipeline is proven.
