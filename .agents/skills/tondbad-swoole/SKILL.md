---
name: Test Tondbād Swoole
description: Set up and run end-to-end tests for the Tondbād Swoole HTTP/gRPC server.
---

# Local testing setup
- PHP 8.3 CLI is required. Install `php8.3-openswoole`, `php8.3-mbstring`, `php8.3-xml`, `php8.3-zip`, `git`, and `unzip`.
- Install Composer: download `composer.phar` with the official installer, then run `php composer.phar install` from the repo root.
- The `vendor/` directory and `ext-openswoole` must be present before the framework can boot.

# Running the HTTP server
- `APP_HTTP_PORT=<port> php public/server.php` starts the OpenSwoole HTTP server on the specified port.
- Equivalent: `APP_HTTP_PORT=<port> php composer.phar run server`.
- `public/index.php` is only a wrapper that requires `public/server.php`.
- Default HTTP port is `9501` unless `APP_HTTP_PORT` is set.

# Running the gRPC server
- `APP_GRPC_PORT=<port> php public/grpc.php` starts the OpenSwoole gRPC server.
- Equivalent: `APP_GRPC_PORT=<port> php composer.phar run grpc`.
- `public/grpc.php` sets `$_ENV['APP_TYPE'] = 'grpc'` so the application kernel boots the gRPC kernel.
- Default gRPC port is `9502` unless `APP_GRPC_PORT` is set.

# Golden-path verification
- `curl http://127.0.0.1:<port>/hello` should return `Hello ` (note the trailing space from the default `name`).
- `curl http://127.0.0.1:<port>/hello/world` should return `Hello world`.
- `curl http://127.0.0.1:<port>/notfound` should return `404 Not Found` with status `404`.
- With `APP_DEBUG=false`, an exception route returns `500 Internal Server Error`; with `APP_DEBUG=true` it appends the exception message.
- gRPC boot: the process should listen on the configured port and log `OpenSwoole GRPC Server is started grpc://0.0.0.0:<port>`.

# Devin Secrets Needed
- None for local testing.
