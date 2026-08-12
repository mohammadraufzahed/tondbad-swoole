# Configuration

All configuration lives in `config/` as PHP files that return arrays. Values are read through `TondbadSwoole\Core\Config` and can be overridden by environment variables.

## Environment mapping

`Env` converts dot-notation config keys to uppercase underscore environment keys:

| Config key | Environment variable | Example |
|---|---|---|
| `app.http.port` | `APP_HTTP_PORT` | `9501` |
| `app.http.host` | `APP_HTTP_HOST` | `127.0.0.1` |
| `database.default` | `DATABASE_DEFAULT` | `mysql` |
| `database.connections.mysql.host` | `DB_MYSQL_HOST` | `127.0.0.1` |
| `cache.default` | `CACHE_DEFAULT` | `phpredis` |
| `queue.default` | `QUEUE_DEFAULT` | `sync` |

Nested arrays are flattened with dot notation and then converted. This lets you override any config value from `.env` or `$_ENV`.

## Reading config

```php
use TondbadSwoole\Core\Config;

$config = app()->container->make(Config::class);

$port = $config->get('app.port', 9501);
$connections = $config->get('database.connections');
```

Helpers:

```php
config('app.port');        // int
config('database.default'); // string
```

## Creating a config file

Add `config/mail.php`:

```php
<?php

declare(strict_types=1);

return [
    'driver' => $env->get('mail.driver', 'smtp'),
    'host' => $env->get('mail.host', 'smtp.example.com'),
    'port' => (int) $env->get('mail.port', 587),
];
```

Then read it anywhere with `config('mail.driver')`.

## Important config files

- `config/app.php` — server type, host, port, timezone, paths
- `config/providers.php` — service provider registration
- `config/database.php` — connection pool and grammar settings
- `config/queue.php` — queue drivers
- `config/cache.php` — cache stores
- `config/auth.php` — guards and user providers
- `config/routes.php` — route file and cache paths
- `config/cors.php` — CORS headers

## Loading order

1. Framework config files in `vendor/mohammadraufzahed/tondbad-swoole/config/` are loaded first.
2. Project config files in `config/` override matching keys.
3. Environment variables override config values.
4. Providers register and boot, then additional config can be modified in `boot()`.
