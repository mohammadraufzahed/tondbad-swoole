<?php

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Route::class, fn() => new Route);
    }

    public function boot(Container $container): void
    {
        $route = $container->make(Route::class);

        $routeClasses = Config::get('routes', []);
        $route::registerAnnotatedRoutes($routeClasses);
        $route->setupDispatcher();
    }
}