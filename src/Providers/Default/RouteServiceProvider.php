<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Route::class, function () use ($container) {
            return new Route(
                $container->make(Container::class),
                $container->make(Config::class),
                $container->make(Logger::class)
            );
        });
    }

    public function boot(Container $container): void
    {
        $route = $container->make(Route::class);
        $routeClasses = $container->make(Config::class)->get('routes', []);

        $route->registerAnnotatedRoutes($routeClasses);
    }
}
