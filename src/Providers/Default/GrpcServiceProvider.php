<?php

namespace TondbadSwoole\Providers\Default;

use OpenSwoole\GRPC\Middleware\{LoggingMiddleware, TraceMiddleware};
use OpenSwoole\GRPC\Server as GrpcServer;
use TondbadSwoole\Core\{Config, Container};
use TondbadSwoole\Providers\Contracts\ServiceProviderInterface;

class GrpcServiceProvider implements ServiceProviderInterface
{

    public function register(Container $container): void
    {
        $container->singleton(GrpcServer::class, function () {
            $server = new GrpcServer('0.0.0.0', 9502);

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

    public function boot(Container $container): void
    {

    }
}