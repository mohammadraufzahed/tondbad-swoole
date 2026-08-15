<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use OpenSwoole\GRPC\Constant;
use OpenSwoole\GRPC\Server as OpenSwooleGrpcServer;
use TondbadSwoole\Core\Container;

final class GrpcServer
{
    private OpenSwooleGrpcServer $server;

    /** @param UnaryServerInterceptor[] $interceptors */
    public function __construct(
        private readonly Container $container,
        private readonly ServiceRegistry $registry,
        private readonly array $interceptors,
        string $host,
        int $port,
        int $mode,
        int $sockType,
        array $settings,
    ) {
        $this->server = new OpenSwooleGrpcServer($host, $port, $mode, $sockType);
        $this->server->set(array_merge(
            [\OpenSwoole\Constant::OPTION_OPEN_HTTP2_PROTOCOL => 1],
            $settings,
        ));

        $registry = $this->registry;
        $this->server->withWorkerContext('tondbad.grpc.registry', function () use ($registry) {
            return $registry;
        });

        $this->server->addMiddleware(new Middleware\Dispatcher($this->container, $this->interceptors));
    }

    public function start(): void
    {
        $this->server->start();
    }

    public function on(string $event, callable $callback): self
    {
        $this->server->on($event, $callback);

        return $this;
    }

    public function shutdown(): void
    {
        $this->server->shutdown();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    public function getOpenSwooleServer(): OpenSwooleGrpcServer
    {
        return $this->server;
    }
}
