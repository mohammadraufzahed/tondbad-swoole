---
name: Test Tondbād Swoole
description: Set up and run end-to-end tests for the Tondbād Swoole HTTP/gRPC server, CLI, queues, and jobs.
---

# Local testing setup
- PHP 8.4 CLI is required (the current lock file resolves Pest/PHPUnit packages that require >= 8.4.1). Install `php8.4-cli`, `php8.4-openswoole`, `php8.4-mbstring`, `php8.4-xml`, `php8.4-zip`, `php8.4-sqlite3`, `git`, and `unzip`.
- `pdo` and `pdo_sqlite` extensions are useful for SQLite queue/migration tests.
- Install Composer and dependencies: `php composer.phar install` from the repo root (or `php composer.phar install --ignore-platform-reqs` if the lock was generated for a different PHP version).
- The `vendor/` directory and `ext-openswoole` must be present before the framework can boot.

# Static checks
- `find . -name '*.php' -not -path './vendor/*' -not -path './.git/*' -print0 | xargs -0 -n1 php -l`
- `php composer.phar validate --strict` (must be run from the repo root so it can find `./composer.json`)
- `php composer.phar test` (runs Pest)

# CLI commands (from repo root)
- Use `php bin/tondbad` (`vendor/bin/tondbad` is not generated when the framework is the root package).
- `php bin/tondbad serve` — start the OpenSwoole HTTP server.
- `php bin/tondbad serve:grpc` — start the OpenSwoole gRPC server.
- `php bin/tondbad route:list` — list all registered routes.
- `php bin/tondbad route:cache` — pre-compile `storage/cache/routes.cache.php`.
- `php bin/tondbad cache:clear` — remove compiled route cache and framework caches.
- `php bin/tondbad --version` / `php bin/tondbad -V` — print the framework version.
- `php bin/tondbad make:controller <Name>` — create `app/Http/Controllers/<Name>Controller.php`.
- `php bin/tondbad make:middleware <Name>` — create `app/Http/Middleware/<Name>Middleware.php`.
- `php bin/tondbad make:provider <Name>` — create `app/Providers/<Name>ServiceProvider.php`.
- `php bin/tondbad make:job <Name>` — create `app/Jobs/<Name>.php` extending `TondbadSwoole\Queue\Jobs\Job`.
- `php bin/tondbad make:guard <Name>` — create `app/Auth/Guards/<Name>GuardFactory.php` implementing `GuardFactory`.
- `php bin/tondbad make:policy <Name>` — create `app/Policies/<Name>Policy.php` implementing `Policy` and using `HandlesAuthorization`.
- `php bin/tondbad hash:make <value>` — generate a bcrypt/argon hash.
- `php bin/tondbad hash:check <value> <hash>` — verify a value against a hash.
- `php bin/tondbad migrate` — run migrations, including the `jobs` and `failed_jobs` tables from the queue provider.
- `php bin/tondbad queue:work --connection=<name> --queue=<name> --max-jobs=<n> --sleep=<sec>` — process queued jobs.

# Server entry points
- `APP_HTTP_PORT=<port> php bin/tondbad serve` starts the OpenSwoole HTTP server.
- `APP_GRPC_PORT=<port> php bin/tondbad serve:grpc` starts the OpenSwoole gRPC server.
- The legacy `public/server.php`, `public/grpc.php`, and `public/index.php` entry points were removed on the `devin/db-engine-pool` branch.

# Database engine / pool verification
- `DatabaseManager` resolves `DatabaseWrapper` via `EngineFactory`; `DatabaseWrapper` checks out a PDO from `SimplePdoPool` (single connection) or `SwoolePdoPool` (coroutine channel) and returns it via `putPdo()` / `close()`.
- `RouteDispatcher` calls `databaseManager->closeOldConnections()` in a `finally` block after each request.
- To verify pool return, expose a test route that uses `db()->connection()` to insert/select rows, then use reflection to read `DatabaseWrapper::$pool` and call `stats()`. After the request `borrowed` should be `0`, `available` at least `1`.
- With SQLite, use a file-backed database (`DB_SQLITE_DATABASE=/path/to/file.sqlite`) so the same database is shared between `php bin/tondbad migrate` and the long-running HTTP server; `:memory:` works only within a single process.

# Configuration
- Default HTTP port is `9501`; default gRPC port is `9502`.
- Override with `APP_HTTP_PORT`, `APP_HTTP_HOST`, `APP_GRPC_PORT`, `APP_GRPC_HOST` environment variables.
- `app.type` must be `http` or `grpc`.
- Set `DB_CONNECTION=sqlite` and `DB_SQLITE_DATABASE=/path/to/file.sqlite` (or use `.env`) to persist SQLite across processes.

# Custom command testing
- Custom commands are auto-discovered from `app/Console/Commands/*.php` under the `App\Console\Commands` namespace.
- Commands can also be listed in `config/app.php` under `commands`.
- Commands that extend `TondbadSwoole\Console\Commands\Command` receive the consumer project `$basePath` through their constructor automatically.
- Commands that implement `TondbadSwoole\Console\CommandInterface` directly (no constructor or with resolvable class dependencies) also work.
- To test, create a file such as `app/Console/Commands/DemoCommand.php`, run `php bin/tondbad`, and invoke `php bin/tondbad demo`.
- Unresolvable commands should be silently skipped, and the CLI should continue listing other commands.

# Queue/job testing
- Create `app/Jobs/<Name>.php` (e.g. via `make:job`) extending `TondbadSwoole\Queue\Jobs\Job`.
- Run `php bin/tondbad migrate` to create `jobs` and `failed_jobs` tables.
- `queue('database')` returns a `DatabaseQueue` using the configured DB connection.
- `$job->dispatch()` uses the sync driver by default and processes `handle()` in the same process.
- `queue:work --connection=database --queue=default --max-jobs=1` pops one job from `jobs` and calls `handle()`.
- A failing `Job` with `tries` set is retried up to that number, then logged to `failed_jobs` and deleted from `jobs`.

# Hashing verification
- `HashManager` is bound as a singleton via `HashServiceProvider` and supports `bcrypt` and `argon`/`argon2id`/`argon2i` drivers.
- Resolve it from the container: `app()->container->make(TondbadSwoole\Support\Hash\HashManager::class)`.
- `$manager->make('secret')` produces a bcrypt hash starting with `$2y$` by default.
- `$manager->check('secret', $hash)` returns `true` for the matching value, `false` otherwise.
- The `hash:make` and `hash:check` CLI commands resolve `HashManager` from the container because PHP's built-in `hash()` function makes a global `hash()` helper impossible.

# Auth/gate/route-binding verification
- Create a `users` table with `id`, `email`, `api_token`, `api_key`, `password`, `name` columns and seed a test user.
- The default auth config uses `guard=token` and `provider=users` (`database` driver), so `TokenGuard` looks for an `Authorization: Bearer <token>` header or an `api_token` query parameter.
- `ApiKeyGuard` looks for an `X-Api-Key` header or an `api_key` query parameter.
- `BasicAuthGuard` looks for an `Authorization: Basic <base64(email:password)>` header and validates the bcrypt password against the `users` table.
- `Authenticate::class` middleware enforces the default guard; `Authenticate::guard('...')` enforces a named guard.
- `#[\TondbadSwoole\Http\Attributes\Authenticate]` on a controller class or method is evaluated by `HandlerInvoker` for array handlers (`[Class::class, 'method']`).
- `auth()` returns the `AuthManager`; `auth('guard')->check()` returns a guard. `gate()` returns the `Gate` service for ability/policy checks.
- Route model binding works for `TondbadSwoole\Database\Model` subclasses that implement `TondbadSwoole\Routing\Contracts\UrlRoutable` (usually via `TondbadSwoole\Routing\Concerns\HasRouteBinding`): type-hint a route parameter, e.g. `function (User $user, Response $response) { ... }` on a route with `/user/{user}`.
- For separate `api_keys` table support, use the `api_keys` provider driver (`TondbadSwoole\Auth\UserProviders\ApiKeyUserProvider`) which allows many keys per user and optional expiration.
- `AuthManager::extend()` accepts a closure or a class implementing `GuardFactory` to register custom guards.
- Guards cache the resolved user in the current `Context` under a fixed per-guard key, not `spl_object_id($request)`, so `RouteDispatcher` clearing the context at the start/end of every request keeps authentication isolated under OpenSwoole.
- Missing-token requests throw `AuthorizationException`, which the `ErrorHandler` converts to a `403` response with the message `This action is unauthorized.`.

# Golden-path verification
- `curl http://127.0.0.1:<port>/hello` should return `Hello ` (note the trailing space from the default `name`).
- `curl http://127.0.0.1:<port>/hello/world` should return `Hello world`.
- `curl http://127.0.0.1:<port>/notfound` should return `404 Not Found` with status `404`.
- With `APP_DEBUG=false`, an exception route returns `500 Internal Server Error`; with `APP_DEBUG=true` it appends the exception message.
- gRPC boot: the process should listen on the configured port and log `OpenSwoole GRPC Server is started grpc://0.0.0.0:<port>`.

# Full end-to-end verification
- `php bin/tondbad`, `--version`, `route:list`, `migrate`, `migrate:rollback`, `migrate:fresh`, `queue:work`, `schedule:work --run-once`, `make:model`, `make:controller`, `serve`, and `serve:grpc` should all exit `0`.
- HTTP server on `127.0.0.1:9501` should serve `GET` and `POST` routes; `/db` and `/pool` confirm the database wrapper returns the PDO to the pool after each request.
- `cache()->set('key', 'value', $ttl)` with a positive TTL stores the value; `cache()->set('key', 'value', 0)` or a negative TTL deletes the key (PSR-16 semantics).
- ORM CRUD, relations (`with('posts.comments')`), and identity map (`User::find(1) === User::find(1)`) work through `EntityManager`/`Model`.
- `queue:work --connection=redis --concurrency=8 --max-jobs=<n> --stop-when-empty` processes pushed `Job` instances with zero duplicates; `queue:status --connection=redis` shows `failed: 0`.
- `schedule:work --run-once` with a `routes/console.php` closure schedules a `call()` event every minute.

# Stress/throughput verification (PR #42 and later)
- HTTP stress: use `OpenSwoole\Coroutine::run()` and `OpenSwoole\Coroutine\Http\Client` to fire many concurrent requests; expect ~4k RPS on simple routes and ~45 RPS on routes that hit the DB, cache, and pool stats.
- Redis queue stress: start `tondbad-redis` on `127.0.0.1:6379`, push `QueueStressJob` to Redis DB 2 (or a fresh DB), then run `queue:work --connection=redis --concurrency=8 --max-jobs=<n> --stop-when-empty`. With the per-coroutine `Predis\Client` fix, 500 jobs processed at ~370 jobs/sec with zero duplicates.
- Redis cache stress: do **not** share a single `Predis\Client` across coroutines; create a fresh `PredisCache` per coroutine (or a fresh `Predis\Client`) to avoid socket contention/hang. With per-coroutine clients, 20 coroutines × 1000 set/get ops completed in ~55 ms (~36k ops/sec).
- In-memory cache stress: `InMemoryCache` is backed by `OpenSwoole\Table` with `config('cache.in_memory.size')` default 1024. `set()` proactively cleans expired entries and evicts existing keys when the table reaches `size`, so writes succeed even when the workload briefly exceeds the configured size. The table retains at most `size` live entries; treat `cache.in_memory.size` as the capacity ceiling and scale it (or use `PredisCache`/Redis) if you need more entries resident.
- ORM/DB stress: use a route that runs `find/flush/clear` or insert cycles in a loop; 200 find/flush/clear cycles against SQLite completed in ~40 ms with zero memory growth.
- gRPC stress: only possible if gRPC services are registered; the current server boots but has no services, so stress can only confirm the listener stays up under connection attempts.

# Generator notes
- `make:controller`, `make:middleware`, `make:provider`, `make:guard`, and `make:policy` strip redundant suffixes and `ucfirst` the base name, so `TestController`, `JwtGuard`, or `BillingProvider` produce `TestController.php`, `JwtGuardFactory.php`, and `BillingServiceProvider.php` respectively.
- For `make:*` commands to resolve at runtime, add `"App\\": "app/"` to `composer.json` `autoload.psr-4` and run `composer dump-autoload`; remove the mapping before committing.

# Routing next-level API quirks
- `RouteDefinition::name()` can be chained on route definitions (e.g. `$route->get('/path', handler)->name('foo')`).
- `#[Controller(..., guards: [...])]` guard classes are enforced by `HandlerInvoker::ensureGuards()` alongside method-level `#[Guard(...)]` attributes.
- `$route->fallback(...)` registers the catch-all `/{path:.*}` route as a normal cached route, so `route:cache` preserves the fallback handler across server restarts.
- `route:cache` compiles `storage/cache/routes.cache.php` and deletes the existing file before rebuilding (`RouteRegistrar::warmCache()`), so route changes are reflected after a server restart; remove `storage/cache/routes.cache.php` to return to uncached routing.
- The fallback catch-all catches unknown paths and parameter-constraint misses (e.g. `/orders/abc` against `/orders/{order}` with `->whereNumber('order')` returns the configured fallback response).
- `make:controller` emits the new `#[Controller('/{slug}')]` + `#[Get]` style (the command replaces `{Name}`, `{Slug}`, and `{slug}` in `stubs/controller.stub`).
- `ResourceRegistrar::pluralToSingular` correctly handles `es`-ending resources such as `articles` -> `article`, `boxes` -> `box`, and `categories` -> `category`.
- Middleware used by the route pipeline (including middleware groups) must implement `TondbadSwoole\Contracts\MiddlewareInterface` and define `process(Request $request, Response $response, callable $next): void`. Route guard classes must implement `TondbadSwoole\Routing\Contracts\Guard` and define `can(Request $request): bool`.

# Unified cache layer (`devin/cache-unified`)
- `cache()` returns a `CacheContract` backed by `HybridStore`: L1 `InMemoryCache` (`OpenSwoole\Table`) -> L2 `RedisCache` (Predis pool) -> loader.
- `cache()->getOrSet($key, fn(CacheItem $item) => ..., $ttl)` returns the cached value or computes it once; the callback can set `lifetime($seconds, $refreshRatio)`, `tag(...$tags)`, and `weight($w)` through the `CacheItem`.
- `cache()->invalidateTags(['users'])` increments tag versions and clears L1; subsequent `get`/`getOrSet` for tagged entries recompute.
- `cache()->refresh($key)` deletes the key and records a refresh in `CacheStats`; the next `getOrSet` reloads it.
- `cache()->stats()` returns `CacheStats` with `hitCount`, `l1HitCount`, `l2HitCount`, `missCount`, `loadCount`, `hitRate()`, etc.
- `CACHE_DEFAULT=redis` enables Redis as L2; ensure `REDIS_HOST`/`REDIS_PORT` point to a running Redis and `redis.pool.size` is set.
- The cache CLI commands `cache:status`, `cache:forget-tags`, and `cache:clear` are registered automatically. `cache:forget-tags` and `cache:clear` automatically wrap Redis operations in `Coroutine::run()` with `Runtime::enableCoroutine(SWOOLE_HOOK_TCP)` so they work from the CLI.
- `cache:status` prints the current in-process `CacheStats`; values are zero from a fresh CLI process unless `cache()` has been used in that process.
- `InMemoryCache` only starts its background expiry `Timer` when constructed inside an active coroutine, so it is safe to use from CLI commands.
- Run integration Redis tests with `RUN_INTEGRATION_TESTS=1 php vendor/bin/pest tests/Integration/CacheRedisTest.php`; they rely on `tests/Support/CacheConcurrencyScript.php` and `CacheRedisTagsScript.php`.
- `cache:forget-tags` and `cache:clear` from the CLI update Redis tag versions / flush Redis, but they cannot clear the `InMemoryCache` L1 tables held by already-running HTTP worker processes; restart the server or wait for L1 TTL expiry to guarantee those workers observe the invalidation.

# Auth end-to-end verification (`devin/auth-unified`)
- Create a fresh consumer project at `/tmp/tondbad_consumer_auth` with a path repository to the framework, `"App\\": "app/"` in `autoload.psr-4`, and `config/auth.php` overriding `defaults.guard` to `session`.
- Set `AUTH_SESSION_STORE=database`, `DB_CONNECTION=sqlite`, and `DB_SQLITE_DATABASE=/tmp/tondbad_consumer_auth/storage/database.sqlite`; ensure the `storage/` directory is writable.
- Provide a `users` migration with `id`, `email` (unique), `password`, `name`, `created_at`, `updated_at`; use nullable/default `0` for the timestamp columns or include them in sign-up data.
- Run `php bin/tondbad migrate` from the consumer directory; tables `users`, `sessions`, `refresh_tokens`, `identities`, and `mfa_factors` are created by the framework's auth migrations.
- Start the server with `APP_HTTP_PORT=9501 php bin/tondbad serve` from the consumer directory; all auth HTTP tests then hit `http://127.0.0.1:9501`.
- Test email/password flow: `POST /auth/register` or `/auth/login` returns an `AuthSession` JSON with `session_id`, `access_token`, `refresh_token`, and `anti_csrf`; subsequent `GET /me` with the `session_id` cookie should return the user.
- Test route guards with `AuthRouteGuard`, `RoleGuard::for('admin')`, `ScopeGuard::for('users:read')`, `#[Authenticate]`, `#[Authorize('view-dashboard')]`, `#[RequireMfa]`, `#[CurrentUser]`, and `VerifyCsrfToken` middleware.
- Test refresh-token rotation: `POST /auth/refresh` with a valid `refresh_token` returns a new `access_token`/`refresh_token`; reusing the old `refresh_token` returns `401` and revokes the family.
- Test revocation: `POST /auth/revoke` with a `session_id` deletes the session and revokes its refresh tokens, so `GET /me` with the old cookie returns `403`.
- Test stateless API access: `POST /api/token` while authenticated returns a bearer `access_token`; `GET /api/me` with `Authorization: Bearer <token>` and an `AuthRouteGuard('access_token')` resolves the user.
- Test MFA email factor: `POST /mfa/challenge {type: "email"}` returns a code; `POST /mfa/verify {type, code}` sets `mfa_verified` on the session; a `#[RequireMfa]` route then returns `200`.
- Test CSRF: `POST /csrf-protected` without `X-CSRF-Token` (or `_token` / `csrf_token` query) returns `403`; a request with the `anti_csrf` value from the session returns `200`.
- Test CLI: `php bin/tondbad auth:clear-sessions` deletes all `sessions` rows and marks every `refresh_tokens` row revoked.
- Concurrent `GET /me` requests for two different sessions must return the matching user (`alice@example.com` vs `bob@example.com`) — this confirms per-coroutine `Context` isolation.

## OIDC / social login verification
- `IdentityBroker` stores `state`/`verifier` in the configured `CacheInterface` (e.g. `HybridStore` with `InMemoryCache` or Redis) instead of per-coroutine `Context`. For in-memory state to survive across the redirect and callback, start the server with `app.http.settings.worker_num => 1` or use Redis as the cache driver.
- Pass a **full absolute URL** as the `$callbackUrl` to `auth()->via('google')->redirect($callbackUrl)` and `->callback($code, $state, $callbackUrl)`. A relative path (e.g. `/auth/google/callback`) will be interpreted by the provider as its own host and the browser will not return to the auth server.
- `IdentityBroker::callback($code, $state, $callbackUrl)` expects `(code, state, callbackUrl)` in that order; passing `$callbackUrl` as the second argument causes `Invalid OIDC state.` because the callback URL is compared to the cached `state`.
- The order of parameters in `IdentityBroker::callback` is also the order `GenericIdentityProvider` uses internally: `$provider->callback($code, $callbackUrl, $state, $verifier)`.

## Route cache caveat
- `Route` uses FastRoute's `cachedDispatcher` with `config('app.route_cache_file')`. If you edit `routes/http.php` while the server is running or between restarts, delete `storage/cache/routes.cache.php` (or set `app.route_cache_file` to `null` for the test environment) before the next start, otherwise stale route definitions are used.

# Unified validation layer (`devin/validation-unified`)
- Create a fresh consumer project with a path repository to the framework, `composer install`, and `config/app.php` that includes `name`, `type`, `debug`, `key`, `middlewares`, `commands`, `route_cache_file`, `framework_cache_dir`, `logging.path`, `http.host/port`, and `grpc.host/port` so `App::validateConfiguration()` passes. Set `app.http.settings.worker_num` to `1` for in-memory state tests.
- `routes/http.php` must **return a callable** that receives `TondbadSwoole\Core\Route\Route $route` and defines routes.
- `Route::whereSchema('param', Schema::int()->gte(1))` validates route parameters before the handler and returns `404` for invalid values.
- `Request::validateSchema(Schema::object([...])->lax())` returns validated/typed data and throws `ValidationException` with structured errors (`{field, rule, message, params}`).
- `#[Field(alias: ..., transform: 'trim|strtolower', rules: 'email', default: 18)]` on `FormRequest` properties enables Pydantic-style hydration; `DtoFactory::make()` builds typed DTOs from the same attributes.
- Legacy `$request->validate(['email' => 'required|email'])` continues to work and also throws `ValidationException`.
- `Config::validate('app.http.port', Schema::int()->gte(1)->lte(65535))` and `Env::getInt()`/`getBool()`/`getString()` exercise the config/env schema integration.
- `DatabaseManager` validates every connection config with `Schema` when `connection()` is first used.
- `EmailPasswordStrategy` and `ApiKeyStrategy` validate credentials with `Schema` before touching the provider; a fake `UserProvider` that throws on `retrieveByCredentials` can be used to prove invalid input is rejected before the provider is reached.
- `App::validateConfiguration()` fails fast with `ConfigurationException` when `app.http.port` etc. are invalid.
- `php benchmarks/validation.php` compares `Schema::safeParse()` against the legacy `Validator` and prints timing/memory stats.
- `queue:work --rate-limit=max:window` validates the option with `Schema`; invalid values now throw `InvalidArgumentException`, print a clear error (e.g. `Invalid --rate-limit value: ...`), and exit `1`.

# Unified event dispatcher verification (`devin/events-next-level`)
- `EventServiceProvider` auto-discovers `app/Listeners/*.php`, instantiates the class via the container, and calls `subscribe($class)`. Listeners may implement `TondbadSwoole\Events\Contracts\EventSubscriber` or be plain classes; methods decorated with `#[Listener]` are also detected.
- The dispatcher is a single singleton: typed events (e.g. `RouteEvent`, `AuthEvent`, `CacheEvent`, `QueueEvent`, `OrmEvent`, `ConsoleEvent`, `GrpcEvent`) are dispatched as objects, and string/wildcard listeners are dispatched as `GenericEvent`.
- Framework modules emit typed events:
  - `route.dispatching`, `route.matched`, `route.dispatched` from `RouteDispatcher`
  - `auth.login`, `auth.logout` from `AuthManager`
  - `cache.hit`, `cache.miss`, `cache.set`, `cache.clear` from `HybridStore`
  - `queue.job.added`, `queue.job.active`, `queue.job.completed`, `queue.job.failed`, etc. from `Queue`
  - `orm.prePersist`, `orm.postPersist`, `orm.onFlush`, `orm.postFlush` from `EntityEventManager`/`UnitOfWork`
  - `console.starting`, `console.terminated`, `console.failed`, `console.not_found` from `Console\Application`
  - `grpc.request`, `grpc.response` from `RouteGrpcMiddleware`
- To trace events end-to-end, create an `app/Listeners/EventTraceSubscriber` that writes to a table such as `event_traces` from each handler. To listen to a typed event by class, subscribe to the event class name (e.g. `RouteEvent::class`); the handler can call `$event->name()` to get the string event name (`route.matched`).
- gRPC testing can use `OpenSwoole\Coroutine\Http2\Client` to send HTTP/2 `POST` to `/service/method` with `content-type: application/grpc+json`, `te: trailers`, and a body of `pack('CN', 0, strlen($json)) . $json` (5-byte gRPC length prefix). The response body also starts with the same 5-byte prefix.
- `CacheClearCommand` calls `file_exists()` and `unlink()` on `config('app.route_cache_file')`; set this to a string path (e.g. `storage/cache/routes.cache.php`) or `cache:clear` will fatal with a `null` argument error.
- `Model` with typed public properties bypasses `__get()`/`__set()` magic, so read persisted values with `getKey()` or `getAttribute('name')` after `em()->persist()->flush()` instead of `$model->id` or `$model->name`.

# Benchmark module verification (`devin/benchmark-module`)
- Run benchmarks from the repo root with `php bin/tondbad benchmark`. It auto-discovers classes annotated with `#[Benchmark]` in `benchmarks/` and `app/Benchmarks/`.
- Console output shows `Benchmark`, `Mode`, `Cnt`, `Score`, `Error`, `Unit`, `Ops/s`, and `Outliers` for each scenario. `Cnt` equals `iterations × invocations` (multiplied by `forks` when forking).
- Use `--iterations=N`, `--warmup=N`, `--invocations=N`, `--forks=N`, `--mode=avg|throughput|sample|single`, `--timeUnit=us|ms|ns|s` to override the `#[Benchmark]` defaults.
- Filter to one benchmark by passing a class/name fragment as the positional argument, e.g. `php bin/tondbad benchmark EventDispatcherBenchmark`.
- `--forks=2` spawns child PHP processes via `proc_open([PHP_BINARY, 'bin/benchmark-runner.php', <base64-encoded scenario>])`; the child re-runs the scenario and returns JSON.
- `--format=json --output=/tmp/bench.json` writes machine-readable results; `--format=md --output=/tmp/bench.md` writes a markdown table.
- `--save-baseline=main.json` writes results to `storage/benchmarks/main.json`.
- `--baseline=main.json` compares the current run against the saved baseline. With `--threshold=0.05` (default), the command exits `0` and prints `No regressions detected.` if every mean is within 5%; otherwise it exits `1` and prints each regression.
- To sanity-check regression detection, save a baseline, manually set a result's `mean` to `0.000001` in a copy, then run with `--baseline=copy.json --threshold=0` and confirm exit code `1` and `Performance regressions detected`.

# Module-specific benchmarks (`devin/module-benchmarks`)
- Run `php bin/tondbad benchmark` from the repo root to discover and execute the module benchmarks in `benchmarks/`:
  - `AuthBenchmark` (`benchIssueApiToken`, `benchCheck`) — boots `BenchmarkApp`, runs migrations, seeds `users`, and issues/checks API tokens.
  - `CacheBenchmark` (`benchGet`, `benchSet`) — in-memory cache `get`/`set`.
  - `ConsoleBenchmark` (`benchRun`) — dispatches a no-op console command.
  - `EventDispatcherBenchmark` (`benchDispatch`) — plain `Dispatcher` dispatch.
  - `GrpcBenchmark` (`benchGrpcRouteDispatch`) — dispatches a gRPC route via `GrpcHttpRequest`/`GrpcHttpResponse`.
  - `OrmBenchmark` (`benchPersist`, `benchFind`, `benchUpdate`) — creates `benchmark_products` via `SchemaTool` and runs EM operations.
  - `QueueBenchmark` (`benchPush`, `benchPop`) — boots `BenchmarkApp`, migrates, and pushes 10k `NoopQueueJob`s before benchmarking.
  - `RoutingBenchmark` (`benchRouteDispatch`) — registers `/users/{id}` and dispatches via `RouteDispatcher`.
  - `SchedulerBenchmark` (`benchDueEvents`, `benchRunDueEvents`) — builds a `Schedule` with 50 call events.
  - `ValidationBenchmark` (`benchSchema`, `benchValidator`) — compares `Schema::safeParse` with the legacy `Validator`.
- All module benchmarks boot the framework through `benchmarks/Support/BenchmarkApp.php` using SQLite `:memory:`, in-memory cache, and the database queue driver.
- `BenchmarkCommand::classNameFromFile` now resolves namespace-less benchmark files (e.g. `benchmarks/AuthBenchmark.php`) by returning `AuthBenchmark`, so a file path works as the positional target: `php bin/tondbad benchmark benchmarks/AuthBenchmark.php`.
- When comparing against a saved baseline, the default `--threshold=0.05` can flag natural benchmark variance as a regression. For stable pass/fail comparisons, use `--threshold=0.10` or higher; for a strict regression gate, keep the default or lower.

# Scheduler (`devin/scheduler-rewrite`)
- `routes/console.php` must return a callable that receives `TondbadSwoole\Scheduling\Schedule` and registers schedules (e.g. `$schedule->call(fn () => ...)->everyMinute()`).
- `php bin/tondbad schedule:work --run-once` runs one poll tick. It accepts `--sleep=N`, `--max-runs=M`, and `--node-id=<id>`.
- `schedule:list`, `schedule:run <id>`, `schedule:pause <id>`, `schedule:resume <id>`, and `schedule:delete <id>` are registered CLI commands.
- `ScheduleWorkCommand` uses `SchedulerWorker` and accepts `--node-id`; it dispatches `ScheduledJob` for closure/callable tasks unless `queue.default` is `sync` (which runs them in-process).
- `SchedulerWorker` with a non-null `nodeId` performs an atomic `claim()` on the configured `ScheduleStore`, preventing duplicate execution across clustered workers.
- `ScheduledJob` rehydrates `ClosureTask` from the `ScheduleRegistry`, so `routes/console.php` must also be loaded in queue worker processes for closure schedules to resolve.
- Closure schedules get a stable `closureId` (`closure-1`, `closure-2`, ...) derived from the registration order in `routes/console.php`, so the same closure is resolved across `schedule:work` and `queue:work` processes as long as the file is loaded in the same order.
- `Schedule::addEvent()` merges existing store state (status, last run, locks) before upserting, but persistence depends on a working `ScheduleStore` implementation.
- `withoutOverlapping()` uses the configured `LockProvider` (`file` by default, `SCHEDULE_LOCKS=redis` for distributed). Two concurrent `schedule:work --run-once` processes against the same schedule should result in only one execution.
- `SchedulerBenchmark` runs from `php bin/tondbad benchmark benchmarks/SchedulerBenchmark.php`.
- `ScheduleWorkCommand` no longer enables OpenSwoole runtime hooks or wraps execution in `Coroutine::run`, which prevents `InMemoryCache` timers from keeping one-off `schedule:work --run-once` processes alive when scheduled commands such as `cache:clear` resolve the cache.
- `ScheduleDefinition::toArray()` serializes run/lock timestamps and `DatabaseScheduleStore` maps them to the `scheduled_jobs` migration columns; `Builder::exists()` is available for the upsert existence check.
- `ScheduleDefinition::fromArray()` treats empty strings as `null` for timezone and all datetime fields, so `RedisScheduleStore` (and any store using empty-string-nulls) hydrates correctly.
- `CronTrigger::getNextRunDate` supports `allowCurrentDate` so `warmNextRunDates()` can include the current minute while post-run scheduling advances to the next occurrence.

# Devin Secrets Needed
- None for local testing.
