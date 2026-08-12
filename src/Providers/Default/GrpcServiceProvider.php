<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use OpenSwoole\GRPC\Server as GrpcServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class GrpcServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $config = $container->make(Config::class);

        if ($config->get('app.type', 'http') !== 'grpc') {
            return;
        }

        $container->singleton(GrpcServer::class, function () use ($container, $config) {
            $server = new GrpcServer(
                (string) $config->get('app.grpc.host', '0.0.0.0'),
                (int) $config->get('app.grpc.port', 9502),
                (int) $config->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
                (int) $config->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
            );

            $settings = $config->get('app.grpc.settings', []);

            if ($settings !== []) {
                $server->set($settings);
            }

            foreach ($config->get('grpc.middlewares', []) as $middleware) {
                $server->addMiddleware($container->make($middleware));
            }

            foreach ($config->get('grpc.services', []) as $service) {
                $server->register($service, $container->make($service));
            }

            return $server;
        });
    }
}
