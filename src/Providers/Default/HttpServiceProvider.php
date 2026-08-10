<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use OpenSwoole\Http\{Request, Response};
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class HttpServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $config = $container->make(Config::class);

        if ($config->get('app.type', 'http') !== 'http') {
            return;
        }

        $container->singleton(HttpServer::class, function () use ($container, $config) {
            $server = new HttpServer('0.0.0.0', (int) $config->get('app.http.port', 8000));

            $this->setupRouter($server, $container);

            return $server;
        });
    }

    private function setupRouter(HttpServer $server, Container $container): void
    {
        $route = $container->make(Route::class);

        $server->on('message', function () {
        });

        $server->on(
            'request',
            fn(Request $request, Response $response) => $route->dispatch($request, $response)
        );
    }

    public function boot(Container $container): void
    {
        $config = $container->make(Config::class);

        if ($config->get('app.type', 'http') !== 'http') {
            return;
        }

        $server = $container->make(HttpServer::class);
        $logger = $container->make(Logger::class);

        $this->setupLogs($server, $logger);
    }

    private function setupLogs(HttpServer $server, Logger $logger): void
    {
        $server->on('start', function (HttpServer $server) use ($logger) {
            $logger->info('Server has started.', ['master_pid' => $server->master_pid, 'port' => $server->port]);
        });
    }
}
