<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Grpc\ServerBuilder;
use TondbadSwoole\Grpc\GrpcServer;
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
            $builder = (new ServerBuilder($container))
                ->forPort(
                    (string) $config->get('app.grpc.host', '0.0.0.0'),
                    (int) $config->get('app.grpc.port', 9502),
                )
                ->mode((int) $config->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0))
                ->sockType((int) $config->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0))
                ->set((array) $config->get('app.grpc.settings', []));

            foreach ($config->get('grpc.interceptors', []) as $interceptor) {
                $builder->addInterceptor($interceptor);
            }

            foreach ($config->get('grpc.services', []) as $service) {
                $builder->addService($service);
            }

            if ($config->get('grpc.reflection', false)) {
                $builder->enableReflection();
            }

            if ($config->get('grpc.health', false)) {
                $builder->enableHealth();
            }

            return $builder->build();
        });
    }
}
