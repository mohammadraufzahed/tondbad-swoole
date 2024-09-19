<?php

namespace TondbadSwoole\Providers\Default;

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
        $container->singleton(GrpcServer::class, function () {
            $server = new GrpcServer('0.0.0.0', Config::get('app.port', 8001));

            // Register middlewares
            $server->addMiddleware(new LoggingMiddleware());
            $server->addMiddleware(new TraceMiddleware());

            $grpcServices = Config::get('grpc.services', []);
            foreach ($grpcServices as $service) {
                $server->register($service);
            }

            return $server;
        });
    }
}