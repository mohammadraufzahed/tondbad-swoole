<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server as HttpServer;
use OpenSwoole\Server;
use OpenSwoole\WebSocket\Server as WebSocketServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\View\Live\SseConnectionManager;
use TondbadSwoole\View\Live\WsConnectionManager;

class HttpServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $config = $container->make(Config::class);

        if ($config->get('app.type', 'http') !== 'http') {
            return;
        }

        $container->singleton(HttpServer::class, function () use ($container, $config) {
            $live = (bool) $config->get('view.live.enabled', false);
            $transport = (string) $config->get('view.live.transport', 'http');
            $host = (string) $config->get('app.http.host', '0.0.0.0');
            $port = (int) $config->get('app.http.port', 9501);
            $mode = (int) $config->get('app.http.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0);
            $sockType = (int) $config->get('app.http.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0);

            $server = $live && $transport === 'websocket'
                ? new WebSocketServer($host, $port, $mode, $sockType)
                : new HttpServer($host, $port, $mode, $sockType);

            $settings = array_merge(
                ['enable_coroutine' => true],
                $config->get('app.http.settings', [])
            );

            $server->set($settings);

            $this->setupRouter($server, $container);
            $this->setupLogs($server, $container->make(Logger::class));

            if ($server instanceof WebSocketServer) {
                $this->setupWebSocket($server, $container);
            }

            $this->setupSsePipeMessage($server, $container);

            return $server;
        });
    }

    private function setupRouter(Server $server, Container $container): void
    {
        $route = $container->make(Route::class);

        $server->on(
            'request',
            fn(Request $request, Response $response) => $route->dispatch($request, $response)
        );
    }

    private function setupLogs(Server $server, Logger $logger): void
    {
        $server->on('start', function (Server $server) use ($logger) {
            $logger->info('Server has started.', ['master_pid' => $server->master_pid, 'port' => $server->port]);
        });
    }

    private function setupWebSocket(WebSocketServer $server, Container $container): void
    {
        $manager = $container->make(WsConnectionManager::class);

        $server->on('open', [$manager, 'onOpen']);
        $server->on('message', [$manager, 'onMessage']);
        $server->on('close', [$manager, 'onClose']);
    }

    private function setupSsePipeMessage(Server $server, Container $container): void
    {
        if (!$container->has(SseConnectionManager::class)) {
            return;
        }

        $manager = $container->make(SseConnectionManager::class);
        $manager->setServer($server);

        $server->on('PipeMessage', [$manager, 'onPipeMessage']);
    }
}
