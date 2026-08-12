# Deployment & Operations

## Server types

Tondbād runs on OpenSwoole. The server type is chosen by `config/app.php` or the `APP_TYPE` environment variable:

- `http` — `OpenSwoole\Http\Server`
- `grpc` — `OpenSwoole\GRPC\Server`

## Configuration

`config/app.php`:

```php
<?php

declare(strict_types=1);

return [
    'name' => $env->get('app.name', 'Tondbād'),
    'type' => $env->get('app.type', 'http'),
    'debug' => (bool) $env->get('app.debug', false),
    'middlewares' => [],
    'commands' => [],
    'route_cache_file' => $env->get('app.route_cache_file', $basePath . '/storage/cache/routes.cache.php'),
    'framework_cache_dir' => $env->get('app.framework_cache_dir', $basePath . '/storage/framework'),
    'paths' => [
        'listeners' => $env->get('app.paths.listeners', 'app/Listeners'),
        'commands' => $env->get('app.paths.commands', 'app/Console/Commands'),
    ],
    'namespaces' => [
        'listeners' => $env->get('app.namespaces.listeners', 'App\\Listeners\\'),
        'commands' => $env->get('app.namespaces.commands', 'App\\Console\\Commands\\'),
    ],
    'logging' => [
        'path' => $env->get('app.logging.path', $basePath . '/storage/logs/app.log'),
        'level' => $env->get('app.logging.level', 'info'),
    ],
    'http' => [
        'host' => $env->get('app.http.host', '0.0.0.0'),
        'port' => $env->get('app.http.port', 9501),
        'mode' => $env->get('app.http.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
        'sock_type' => $env->get('app.http.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
        'settings' => [
            'worker_num' => (int) $env->get('app.http.settings.worker_num', 4),
            'max_request' => (int) $env->get('app.http.settings.max_request', 10000),
            'enable_coroutine' => true,
        ],
    ],
    'grpc' => [
        'host' => $env->get('app.grpc.host', '0.0.0.0'),
        'port' => $env->get('app.grpc.port', 9502),
        'mode' => $env->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
        'sock_type' => $env->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
        'settings' => [],
    ],
];
```

Environment variables: `APP_TYPE`, `APP_HTTP_HOST`, `APP_HTTP_PORT`, `APP_HTTP_SETTINGS_WORKER_NUM`, etc.

## Starting the server

```bash
php public/index.php
```

or

```bash
php bin/tondbad serve
```

The host and port are read from `config/app.php`; they are not command arguments.

## Worker model

OpenSwoole spawns a master process, worker processes, and optional task workers. Each worker handles multiple coroutines concurrently. Keep state out of workers; use the `ContextInterface` for per-request state.

## Graceful reload

OpenSwoole supports graceful worker reload with `SIGUSR1`/`SIGUSR2`:

```bash
kill -USR1 $(cat storage/logs/tondbad.pid)
```

> A `reload` command can be added by sending the signal to the running process. PID storage is not built-in; implement it in your bootstrap if needed.

## Logging

Monolog is configured through `app.logging` in `config/app.php`:

```php
$logger = app()->container->make(\Monolog\Logger::class);
$logger->info('Server started', ['port' => 9501]);
```

## Health checks

Add a `/health` route in `routes/http.php`:

```php
$route->get('/health', function (Request $request, Response $response) {
    $db = db();
    $ok = $db->statement('select 1');

    $response->json([
        'status' => $ok ? 'ok' : 'error',
        'time' => time(),
    ]);
});
```

## Metrics

OpenSwoole server stats can be exposed on a route:

```php
$route->get('/metrics', function (Request $request, Response $response) {
    $server = app()->container->make(\OpenSwoole\Http\Server::class);
    $stats = $server->stats();

    $response->json($stats);
});
```

## Process managers

For production, run the server under a process manager such as `systemd`, `supervisord`, or a container orchestrator. Set `restart` policy to `always` and capture logs to `storage/logs/`.

## Docker example

```dockerfile
FROM php:8.2-cli
RUN pecl install openswoole && docker-php-ext-enable openswoole
COPY . /app
WORKDIR /app
RUN composer install --no-dev --optimize-autoloader
EXPOSE 9501
CMD ["php", "public/index.php"]
```

## Common operations

```bash
# Run migrations before starting
php bin/tondbad migrate

# Warm route cache
php bin/tondbad route:cache

# Clear caches
php bin/tondbad cache:clear

# Run queue worker in a separate process
php bin/tondbad queue:work --connection=redis --tries=3

# Run scheduler in a separate process
php bin/tondbad schedule:work
```

## Connection lifecycle

Database connections, cache clients, and other network resources should be created once per request or obtained from the container, not stored in static globals. The `RouteDispatcher` clears `ContextInterface` at the end of each request to prevent state leakage between coroutines.
