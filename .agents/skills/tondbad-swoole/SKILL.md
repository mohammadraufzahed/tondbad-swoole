---
name: Test Tondbād Swoole
description: Set up and run end-to-end tests for the Tondbād Swoole HTTP/gRPC server and CLI.
---

# Local testing setup
- PHP 8.3 CLI is required. Install `php8.3-openswoole`, `php8.3-mbstring`, `php8.3-xml`, `php8.3-zip`, `git`, and `unzip`.
- Install Composer and dependencies: `php composer.phar install` from the repo root.
- The `vendor/` directory and `ext-openswoole` must be present before the framework can boot.

# Static checks
- `find . -name '*.php' -not -path './vendor/*' -not -path './.git/*' -print0 | xargs -0 -n1 php -l`
- `php composer.phar validate --strict`
- `php vendor/bin/phpunit`

# CLI commands (from repo root)
- `php bin/tondbad serve` — start the OpenSwoole HTTP server.
- `php bin/tondbad serve:grpc` — start the OpenSwoole gRPC server.
- `php bin/tondbad route:cache` — pre-compile `storage/cache/routes.cache.php`.
- `php bin/tondbad cache:clear` — remove compiled route cache and framework caches.
- `php bin/tondbad make:controller <Name>` — create `app/Http/Controllers/<Name>Controller.php`.
- `php bin/tondbad make:middleware <Name>` — create `app/Http/Middleware/<Name>Middleware.php`.
- `php bin/tondbad make:provider <Name>` — create `app/Providers/<Name>ServiceProvider.php`.

# Legacy entry points
- `APP_HTTP_PORT=<port> php public/server.php` still starts the HTTP server.
- `APP_GRPC_PORT=<port> php public/grpc.php` still starts the gRPC server.
- `public/index.php` is a wrapper that requires `public/server.php`.

# Configuration
- Default HTTP port is `9501`; default gRPC port is `9502`.
- Override with `APP_HTTP_PORT`, `APP_HTTP_HOST`, `APP_GRPC_PORT`, `APP_GRPC_HOST` environment variables.
- `app.type` must be `http` or `grpc`.

# Custom command testing
- Custom commands are auto-discovered from `app/Console/Commands/*.php` under the `App\Console\Commands` namespace.
- Commands can also be listed in `config/app.php` under `commands`.
- Commands that extend `TondbadSwoole\Console\Commands\Command` receive the consumer project `$basePath` through their constructor automatically.
- Commands that implement `TondbadSwoole\Console\CommandInterface` directly (no constructor or with resolvable class dependencies) also work.
- To test, create a file such as `app/Console/Commands/DemoCommand.php`, run `php bin/tondbad`, and invoke `php bin/tondbad demo`.
- Unresolvable commands should be silently skipped, and the CLI should continue listing other commands.

# Golden-path verification
- `curl http://127.0.0.1:<port>/hello` should return `Hello ` (note the trailing space from the default `name`).
- `curl http://127.0.0.1:<port>/hello/world` should return `Hello world`.
- `curl http://127.0.0.1:<port>/notfound` should return `404 Not Found` with status `404`.
- With `APP_DEBUG=false`, an exception route returns `500 Internal Server Error`; with `APP_DEBUG=true` it appends the exception message.
- gRPC boot: the process should listen on the configured port and log `OpenSwoole GRPC Server is started grpc://0.0.0.0:<port>`.

# Devin Secrets Needed
- None for local testing.
