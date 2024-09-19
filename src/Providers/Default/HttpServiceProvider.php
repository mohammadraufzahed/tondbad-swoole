<?php

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use OpenSwoole\Http\{Request, Response};
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class HttpServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(HttpServer::class, function () use ($container) {
            $server = new HttpServer('0.0.0.0', (int)Config::get('app.port', 8000));

            $this->setupRouter($server, $container);

            return $server;
        });
    }

    private function setupRouter(HttpServer $server, $container)
    {
        $route = $container->make(Route::class);

        $server
            ->on('message', function () {
            });
        $server
            ->on(
                'request',
                fn(Request $request, Response $response) => $route->dispatch($request, $response)
            );
    }

    public function boot(Container $container): void
    {
        $server = $container->make(HttpServer::class);
        $logger = $container->make(Logger::class);

        $this->setupLogs($server, $logger);

    }

    private function setupLogs(HttpServer $server, Logger $logger)
    {
        // Attach event listeners to OpenSwoole server events
        $server->on('start', function (HttpServer $server) use ($logger) {
            $logger->info('Server has started.', ['master_pid' => $server->master_pid, 'port' => $server->port]);
        });
    }
}