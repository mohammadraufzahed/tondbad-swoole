# Service Providers

Providers are the main extension point for bootstrapping services, registering bindings, and running startup logic.

## Provider structure

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Bind services into the container
        $container->bind(\App\Services\PaymentGateway::class, \App\Services\StripeGateway::class);
    }

    public function boot(Container $container): void
    {
        // Runs after all providers register
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
```

## Lifecycle

1. All providers' `register()` methods run.
2. All providers' `boot()` methods run.
3. The application is ready to handle requests, jobs, or commands.

## Registering providers

Add providers to `config/providers.php`:

```php
<?php

declare(strict_types=1);

return [
    \TondbadSwoole\Providers\Default\ConfigServiceProvider::class,
    \TondbadSwoole\Providers\Default\DatabaseServiceProvider::class,
    \TondbadSwoole\Providers\Default\CacheServiceProvider::class,
    \TondbadSwoole\Providers\Default\EventServiceProvider::class,
    \TondbadSwoole\Providers\Default\QueueServiceProvider::class,
    \TondbadSwoole\Providers\Default\ScheduleServiceProvider::class,
    \TondbadSwoole\Providers\Default\RouteServiceProvider::class,
    \TondbadSwoole\Providers\Default\AuthServiceProvider::class,
    \TondbadSwoole\Providers\Default\HashServiceProvider::class,
    \App\Providers\AppServiceProvider::class,
];
```

Order matters: dependencies required by later providers must be registered earlier.

## Loading migrations from a provider

```php
public function boot(Container $container): void
{
    $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
}
```

`DatabaseServiceProvider` collects all registered migration paths and runs them in timestamp order.

## Default providers

| Provider | Responsibility |
|---|---|
| `ConfigServiceProvider` | Loads `config/*.php` into `Config` and sets search paths |
| `DatabaseServiceProvider` | Registers `DatabaseManager`, connection pool, migration paths |
| `CacheServiceProvider` | Binds the configured `CacheInterface` |
| `EventServiceProvider` | Registers the event dispatcher and discovers `app/Listeners` |
| `QueueServiceProvider` | Registers `QueueManager` and default connection |
| `ScheduleServiceProvider` | Registers `Schedule` and loads `routes/console.php` |
| `RouteServiceProvider` | Registers `Route` and loads `routes/http.php` and `routes/grpc.php` |
| `AuthServiceProvider` | Registers `AuthManager`, guards, and user providers |
| `HashServiceProvider` | Binds `HashManager` and the default `Hasher` |
| `HttpServiceProvider` | Builds `OpenSwoole\Http\Server` when `app.type` is `http` |
| `GrpcServiceProvider` | Builds `OpenSwoole\GRPC\Server` when `app.type` is `grpc` |
| `ConsoleServiceProvider` | Builds the `tondbad` CLI and discovers commands |
| `LogServiceProvider` | Configures Monolog with `app.logging` |

## Conditional registration

Use `register()` to decide whether a provider applies:

```php
public function register(Container $container): void
{
    if ($container->make(Config::class)->get('app.type') !== 'http') {
        return;
    }

    // http-only bindings
}
```
