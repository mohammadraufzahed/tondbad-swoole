<?php

namespace TondbadSwoole\Providers\Default;

use Monolog\Logger;
use OpenSwoole\GRPC\Middleware\{LoggingMiddleware, TraceMiddleware};
use OpenSwoole\GRPC\Server as GrpcServer;
use TondbadSwoole\Core\{Config, Container};
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class GrpcServiceProvider extends ServiceProvider
{

    public function register(Container $container): void
    {

        if (Config::get('app.type', 'http') !== 'grpc')
            return;
        $container->singleton(GrpcServer::class, function () use ($container) {
            $server = new GrpcServer('0.0.0.0', Config::get('app.grpc.port', 8001));

            // Register middlewares
            $middlewares = Config::get('grpc.middlewares', []);

            foreach ($middlewares as $middleware)
                $server->addMiddleware($container->make($middleware));

            $grpcServices = Config::get('grpc.services', []);
            foreach ($grpcServices as $service) {
                $server->register($service, $container->make($service));
            }

            return $server;
        });
    }
}
