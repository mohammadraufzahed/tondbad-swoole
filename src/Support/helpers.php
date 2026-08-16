<?php

declare(strict_types=1);

use TondbadSwoole\Auth\Access\Gate;
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Contracts\Guard as GuardContract;
use TondbadSwoole\Auth\Mfa\MfaManager;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\CacheContract;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\EntityManagerInterface;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\DispatchResult;
use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Scheduling\Schedule;
use TondbadSwoole\Scheduling\Scheduler;

if (!function_exists('app')) {
    function app(): ?App
    {
        return App::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()?->container->make(Config::class)->get($key, $default);
    }
}

if (!function_exists('cache')) {
    function cache(): ?CacheContract
    {
        return app()?->container->make(CacheContract::class);
    }
}

if (!function_exists('db')) {
    function db(?string $connection = null): ConnectionInterface|DatabaseManager|null
    {
        $manager = app()?->container->make(DatabaseManager::class);

        if ($manager === null) {
            return null;
        }

        return $connection !== null ? $manager->connection($connection) : $manager;
    }
}

if (!function_exists('em')) {
    function em(): ?EntityManagerInterface
    {
        return app()?->container->make(EntityManagerInterface::class);
    }
}

if (!function_exists('schema')) {
    function schema(): ?SchemaBuilder
    {
        $connection = db();

        if ($connection instanceof ConnectionInterface) {
            return $connection->getSchemaBuilder();
        }

        return $connection?->getSchemaBuilder();
    }
}

if (!function_exists('queue')) {
    function queue(?string $connection = null): ?QueueInterface
    {
        $manager = app()?->container->make(QueueManager::class);

        return $manager?->connection($connection);
    }
}

if (!function_exists('schedule')) {
    function schedule(): ?Schedule
    {
        return app()?->container->make(Schedule::class);
    }
}

if (!function_exists('scheduler')) {
    function scheduler(): ?Scheduler
    {
        return app()?->container->make(Scheduler::class);
    }
}

if (!function_exists('dispatcher')) {
    function dispatcher(): ?EventDispatcher
    {
        return app()?->container->make(EventDispatcher::class);
    }
}

if (!function_exists('event')) {
    function event(string|object $event, mixed $payload = null): ?DispatchResult
    {
        return app()?->container->make(EventDispatcher::class)?->dispatch($event, $payload);
    }
}

if (!function_exists('auth')) {
    function auth(?string $guard = null): AuthManager|GuardContract
    {
        $manager = app()?->container->make(AuthManager::class);

        if ($manager === null) {
            throw new RuntimeException('AuthManager not available.');
        }

        return $guard !== null ? $manager->guard($guard) : $manager;
    }
}

if (!function_exists('gate')) {
    function gate(): ?Gate
    {
        return app()?->container->make(Gate::class);
    }
}

if (!function_exists('mfa')) {
    function mfa(): ?MfaManager
    {
        return app()?->container->make(MfaManager::class);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $route = app()?->container->make(Route::class);

        if ($route === null) {
            throw new RuntimeException('Route registrar not available.');
        }

        return $route->url($name, $parameters, !$absolute);
    }
}

if (!function_exists('signedRoute')) {
    function signedRoute(string $name, array $parameters = [], ?DateTimeInterface $expires = null, bool $absolute = false): string
    {
        $route = app()?->container->make(Route::class);

        if ($route === null) {
            throw new RuntimeException('Route registrar not available.');
        }

        return $route->signedUrl($name, $parameters, $expires, !$absolute);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = app()?->basePath() ?? getcwd() ?: sys_get_temp_dir();

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): \TondbadSwoole\View\View
    {
        $manager = app()?->container->make(\TondbadSwoole\View\ViewManager::class);

        if ($manager === null) {
            throw new RuntimeException('View manager not available.');
        }

        return new \TondbadSwoole\View\View($manager, $view, $data);
    }
}

if (!function_exists('e')) {
    function e(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return session_id() ?: bin2hex(random_bytes(16));
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}

if (!function_exists('classNames')) {
    function classNames(array $classes): string
    {
        $result = [];

        foreach ($classes as $class => $active) {
            if (is_int($class)) {
                $class = $active;
                $active = true;
            }

            if ($active) {
                $result[] = (string) $class;
            }
        }

        return implode(' ', $result);
    }
}

if (!function_exists('attributeString')) {
    function attributeString(array $attributes): string
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $result[] = e($key);
                }
            } elseif ($value === null) {
                continue;
            } else {
                $result[] = e($key) . '="' . e($value) . '"';
            }
        }

        return $result === [] ? '' : ' ' . implode(' ', $result);
    }
}

