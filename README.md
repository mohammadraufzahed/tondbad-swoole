# Tondbād Swoole

**Tondbād Swoole** is a lightweight, high-performance PHP framework built on **OpenSwoole** for asynchronous HTTP applications, microservices, and gRPC servers. It offers a FastRoute-based routing layer, a custom dependency-injection container, service providers, PSR-16 aligned caches, and Monolog logging.

## Features

- Asynchronous HTTP server powered by OpenSwoole.
- FastRoute routing with attribute-based and programmatic route definitions.
- Custom DI container with constructor injection, union types, and optional parameters.
- App-owned `Config` and `Env` instances loaded from `config/` and `.env` files.
- Global HTTP middleware pipeline with a built-in CORS middleware.
- PSR-16 aligned cache drivers: in-memory, Predis, and phpredis.
- Monolog file logging with configurable log level.
- Graceful shutdown via `pcntl_signal` (`SIGTERM`, `SIGINT`).
- gRPC server entry point with OpenSwoole gRPC support.

## Requirements

- PHP 8.2 or higher
- `ext-openswoole`
- `ext-pcntl` (for graceful shutdown)
- Composer
- Redis extension or Predis (optional, only when using Redis cache drivers)

## Installation

Install the package with Composer:

```bash
composer require mohammadraufzahed/tondbad-swoole
```

The OpenSwoole extension is required at runtime:

```bash
pecl install openswoole
```

## Bootstrapping

Create a minimal `public/server.php` in your project:

```php
use TondbadSwoole\Bootstrap\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

AppFactory::create(dirname(__DIR__))->run();
```

`AppFactory::create($basePath)` discovers `config/`, `.env`, and `routes/` from your project root. You may also instantiate `App` directly:

```php
$app = new App(dirname(__DIR__));
$app->run();
```

## Configuration

Create a `.env` in your project root or set values there:

```bash
APP_NAME="Tondbad Swoole"
APP_TYPE=http
APP_DEBUG=false
APP_HTTP_PORT=9501
APP_GRPC_PORT=9502
APP_LOGGING_PATH=/var/log/tondbad/app.log
APP_LOGGING_LEVEL=info
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

See `config/app.php`, `config/cache.php`, and `config/cors.php` for the full list of configurable values.

## Usage

### Running the HTTP Server

```bash
vendor/bin/tondbad serve
# or directly
php public/server.php
```

The HTTP server listens on the host and port configured by `APP_HTTP_HOST` and `APP_HTTP_PORT` (defaults `0.0.0.0:9501`).

### Running the gRPC Server

```bash
vendor/bin/tondbad serve:grpc
# or directly
php public/grpc.php
```

The gRPC server listens on the host and port configured by `APP_GRPC_HOST` and `APP_GRPC_PORT` (defaults `0.0.0.0:9502`).

### Running Tests

```bash
composer test
# or
php vendor/bin/phpunit
```

### Compiling Protocol Buffers (for gRPC)

```bash
composer compile-proto
```

This command compiles `.proto` files in `protos/` and writes generated PHP classes to `generated/`.

## Defining Routes

### Programmatic Routes

Routes can be defined in `routes/http.php` (and `routes/grpc.php` for gRPC):

```php
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

return function (Route $route) {
    $route->addRoute('GET', '/hello[/{name}]', function (Request $request, Response $response, ?string $name = '') {
        $response->html('Hello ' . htmlspecialchars($name ?? ''));
    });
};
```

Alternatively, you can still register routes programmatically in `public/server.php` before calling `$app->run()`.

### Attribute-Based Routes

Routes can also be defined with the `#[Endpoint]` attribute inside controllers:

```php
namespace App\Controllers;

use TondbadSwoole\Core\Route\Attributes\Endpoint;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class HomeController
{
    #[Endpoint('GET', '/')]
    public function index(Request $request, Response $response): void
    {
        $response->html('Welcome to Tondbād Swoole!');
    }

    #[Endpoint('POST', '/submit')]
    public function submit(Request $request, Response $response): void
    {
        $data = $request->json();
        $response->json(['received' => $data]);
    }
}
```

Register controller classes in `config/routes.php`:

```php
return [
    \App\Controllers\HomeController::class,
];
```

### Request and Response Helpers

The framework provides `TondbadSwoole\Http\Request` and `TondbadSwoole\Http\Response` wrappers around the OpenSwoole HTTP objects:

```php
function (Request $request, Response $response) {
    $name = $request->input('name', 'guest');     // post, query, or JSON body
    $token = $request->header('authorization');    // case-insensitive header lookup
    $response->json(['name' => $name, 'token' => $token]);
}
```

Backward compatibility: route handlers may still type-hint `OpenSwoole\Http\Request` and `OpenSwoole\Http\Response` if desired.

## Middleware

Global middleware is configured in `config/app.php`:

```php
'middlewares' => [
    \TondbadSwoole\Core\Http\Middlewares\CorsMiddleware::class,
],
```

A custom middleware implements `TondbadSwoole\Contracts\MiddlewareInterface`:

```php
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): void
    {
        // do something before the route handler
        $next($request, $response);
    }
}
```

Use the CLI to generate a stub:

```bash
vendor/bin/tondbad make:middleware Log
```

## Configuration

Configuration files live in `config/` and are resolved with dot notation:

```php
$config->get('app.name');
$config->get('app.http.port', 9501);
$config->get('cors.allowed_origins', ['*']);
```

Environment values in `.env` override config file values. The `Env` class maps dot notation to uppercase underscores, e.g. `app.http.port` reads `APP_HTTP_PORT`.

Service providers are registered in `config/providers.php`. They can implement `register()` and `boot()` lifecycle hooks.

## Logging

Logging uses Monolog. By default, logs are written to the path configured by `app.logging.path` (`APP_LOGGING_PATH`). The log level is controlled by `app.logging.level` (`APP_LOGGING_LEVEL`).

## Graceful Shutdown

The framework registers `pcntl_signal` handlers for `SIGTERM` and `SIGINT` so the OpenSwoole server can shut down cleanly.

## Cache

The cache layer is aligned with PSR-16 and supports multiple drivers:

- `TondbadSwoole\Core\Cache\InMemoryCache`
- `TondbadSwoole\Core\Cache\PredisCache`
- `TondbadSwoole\Core\Cache\PhpRedisCache`

Drivers are bound through service providers and configured from `config/cache.php`.

## Directory Structure

```
config/                Configuration files
public/                HTTP and gRPC entry points
public/examples/       Example scripts for caches and gRPC client
src/                   Framework source
src/Bootstrap/         Application bootstrap
src/Console/           CLI commands
src/Core/              Core systems (Config, Env, Container, Route, Cache)
src/Http/              Request/Response wrappers
src/Providers/         Service providers
routes/                HTTP and gRPC route files
tests/                 PHPUnit test suite
storage/cache/         Route cache and runtime cache files
storage/logs/          Application logs
storage/framework/     Framework runtime files
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

This project is licensed under the MIT License.
