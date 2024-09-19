<?php

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route;
use TondbadSwoole\Providers\Contracts\ServiceProviderInterface;
use OpenSwoole\WebSocket\Server as HttpServer;
use OpenSwoole\Http\{Request, Response};
use Monolog\Logger;

class HttpServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(HttpServer::class, function () use ($container) {
            $server = new HttpServer('0.0.0.0', 9501);

            $this->setupRouter($server, $container);

            return $server;
        });
    }

    public function boot(Container $container): void
    {
        $server = $container->make(HttpServer::class);
        $logger = $container->make(Logger::class);

        $this->setupLogs($server, $logger);

    }

    private function setupRouter(HttpServer $server, $container)
    {
        $route = $container->make(Route::class);

        $server
            ->on('message', function () {});
        $server
            ->on(
                'request',
                fn(Request $request, Response $response) => $route->dispatch($request, $response)
            );
    }

    private function setupLogs(HttpServer $server, Logger $logger)
    {
        // Attach event listeners to OpenSwoole server events
        $server->on('start', function (HttpServer $server) use ($logger) {
            $logger->info('Server has started.', ['master_pid' => $server->master_pid]);
        });
    }
}