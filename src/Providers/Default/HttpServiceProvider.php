<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server as HttpServer;
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
            $server = new HttpServer(
                (string) $config->get('app.http.host', '0.0.0.0'),
                (int) $config->get('app.http.port', 9501),
                (int) $config->get('app.http.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
                (int) $config->get('app.http.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
            );

            $settings = array_merge(
                ['enable_coroutine' => true],
                $config->get('app.http.settings', [])
            );

            $server->set($settings);

            $this->setupRouter($server, $container);
            $this->setupLogs($server, $container->make(Logger::class));

            return $server;
        });
    }

    private function setupRouter(HttpServer $server, Container $container): void
    {
        $route = $container->make(Route::class);

        $server->on(
            'request',
            fn(Request $request, Response $response) => $route->dispatch($request, $response)
        );
    }

    private function setupLogs(HttpServer $server, Logger $logger): void
    {
        $server->on('start', function (HttpServer $server) use ($logger) {
            $logger->info('Server has started.', ['master_pid' => $server->master_pid, 'port' => $server->port]);
        });
    }
}
