<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Core\Route\RouteLoader;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Route::class, function () use ($container) {
            $route = new Route(
                $container->make(Container::class),
                $container->make(Config::class),
                $container->make(Logger::class)
            );

            $basePath = $container->make(App::class)->basePath();
            $config = $container->make(Config::class);
            $appType = $config->get('app.type', 'http');
            $loader = new RouteLoader();

            if ($appType === 'http' && file_exists($basePath . '/routes/http.php')) {
                $loader->load($basePath . '/routes/http.php', $route);
            }

            if ($appType === 'grpc' && file_exists($basePath . '/routes/grpc.php')) {
                $loader->load($basePath . '/routes/grpc.php', $route);
            }

            $route->registerAnnotatedRoutes($config->get('routes', []));

            return $route;
        });
    }
}
