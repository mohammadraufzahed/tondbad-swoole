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
- `RouteDefinition::name()` now calls `Route::setName()` and can be chained on route definitions (e.g. `$route->get('/path', handler)->name('foo')`).
- `#[Controller(..., guards: [...])]` guard classes are enforced by `HandlerInvoker::ensureGuards()` in addition to any method-level `#[Guard(...)]` attributes.
- `$route->fallback(...)` registers the catch-all `/{path:.*}` route as a normal cached route, so `route:cache` preserves the fallback handler across server restarts.
- `route:cache` works for directly-registered routes and the fallback. To rebuild, run `php bin/tondbad route:cache` and restart the server; remove `storage/cache/routes.cache.php` to go back to uncached routing.
- The fallback catch-all catches paths whose first segment does not match any defined static route. Parameter-constraint failures on routes whose static prefix still matches (e.g. `/orders/{order}` with `->whereNumber('order')` and `/orders/abc`) currently return the framework's default `404 Not Found` rather than the fallback handler, because FastRoute groups routes by static segment.

# Devin Secrets Needed
- None for local testing.
