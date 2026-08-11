---
name: Test Tondbād Swoole
description: Set up and run end-to-end tests for the Tondbād Swoole HTTP/gRPC server, CLI, queues, and jobs.
---

# Local testing setup
- PHP 8.3 CLI is required. Install `php8.3-openswoole`, `php8.3-mbstring`, `php8.3-xml`, `php8.3-zip`, `git`, and `unzip`.
- `pdo` and `pdo_sqlite` extensions are useful for SQLite queue/migration tests.
- Install Composer and dependencies: `php composer.phar install` from the repo root.
- The `vendor/` directory and `ext-openswoole` must be present before the framework can boot.

# Static checks
- `find . -name '*.php' -not -path './vendor/*' -not -path './.git/*' -print0 | xargs -0 -n1 php -l`
- `php composer.phar validate --strict` (must be run from the repo root so it can find `./composer.json`)
- `php composer.phar test` (runs Pest)

# CLI commands (from repo root)
- Use `php bin/tondbad` (`vendor/bin/tondbad` is not generated when the framework is the root package).
- `php bin/tondbad serve` — start the OpenSwoole HTTP server.
- `php bin/tondbad serve:grpc` — start the OpenSwoole gRPC server.
- `php bin/tondbad route:cache` — pre-compile `storage/cache/routes.cache.php`.
- `php bin/tondbad cache:clear` — remove compiled route cache and framework caches.
- `php bin/tondbad make:controller <Name>` — create `app/Http/Controllers/<Name>Controller.php`.
- `php bin/tondbad make:middleware <Name>` — create `app/Http/Middleware/<Name>Middleware.php`.
- `php bin/tondbad make:provider <Name>` — create `app/Providers/<Name>ServiceProvider.php`.
- `php bin/tondbad make:job <Name>` — create `app/Jobs/<Name>.php` extending `TondbadSwoole\Queue\Jobs\Job`.
- `php bin/tondbad migrate` — run migrations, including the `jobs` and `failed_jobs` tables from the queue provider.
- `php bin/tondbad queue:work --connection=<name> --queue=<name> --max-jobs=<n> --sleep=<sec>` — process queued jobs.

# Legacy entry points
- `APP_HTTP_PORT=<port> php public/server.php` still starts the OpenSwoole HTTP server.
- `APP_GRPC_PORT=<port> php public/grpc.php` still starts the OpenSwoole gRPC server.
- `public/index.php` is a wrapper that requires `public/server.php`.

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

# Golden-path verification
- `curl http://127.0.0.1:<port>/hello` should return `Hello ` (note the trailing space from the default `name`).
- `curl http://127.0.0.1:<port>/hello/world` should return `Hello world`.
- `curl http://127.0.0.1:<port>/notfound` should return `404 Not Found` with status `404`.
- With `APP_DEBUG=false`, an exception route returns `500 Internal Server Error`; with `APP_DEBUG=true` it appends the exception message.
- gRPC boot: the process should listen on the configured port and log `OpenSwoole GRPC Server is started grpc://0.0.0.0:<port>`.

# Devin Secrets Needed
- None for local testing.
