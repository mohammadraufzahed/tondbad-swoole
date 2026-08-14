<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Core\Route\RouteLoader;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Routing\FileRouteLoader;

class RouteServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Route::class, function () use ($container) {
            $route = new Route(
                $container->make(Container::class),
                $container->make(Config::class),
                $container->make(Logger::class),
                $container->make(ContextInterface::class)
            );

            $basePath = $container->make(App::class)->basePath();
            $config = $container->make(Config::class);

            $middlewareGroups = file_exists($basePath . '/config/middleware.php')
                ? require $basePath . '/config/middleware.php'
                : [];

            if (is_array($middlewareGroups)) {
                foreach ($middlewareGroups as $name => $middlewares) {
                    if (is_string($name) && is_array($middlewares)) {
                        $route->middlewareGroup($name, $middlewares);
                    }
                }
            }
            $appType = $config->get('app.type', 'http');
            $loader = new RouteLoader();

            $httpRouteFile = $config->get('routes.http', 'routes/http.php');
            $grpcRouteFile = $config->get('routes.grpc', 'routes/grpc.php');

            if ($appType === 'http' && file_exists($basePath . '/' . $httpRouteFile)) {
                $loader->load($basePath . '/' . $httpRouteFile, $route);
            }

            if ($appType === 'http' && $config->get('routes.file_routes.enabled', false)) {
                $fileRoutePath = $basePath . '/' . $config->get('routes.file_routes.path', 'routes/http');

                if (is_dir($fileRoutePath)) {
                    (new FileRouteLoader())->load($fileRoutePath, $route);
                }
            }

            if ($appType === 'grpc' && file_exists($basePath . '/' . $grpcRouteFile)) {
                $loader->load($basePath . '/' . $grpcRouteFile, $route);
            }

            $controllers = $config->get('routes.controllers', []);
            $route->registerAnnotatedRoutes(is_array($controllers) ? $controllers : []);

            return $route;
        });
    }
}
