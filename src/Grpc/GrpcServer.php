<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use OpenSwoole\HTTP\Server as OpenSwooleHttpServer;
use TondbadSwoole\Core\Container;

final class GrpcServer
{
    private OpenSwooleHttpServer $server;

    private ?ServiceRegistry $registry = null;

    private ?Context $workerContext = null;

    /** @param class-string<BindableService>[] $services */
    /** @param class-string<ServerInterceptor>[] $interceptors */
    public function __construct(
        private readonly Container $container,
        private readonly array $services,
        private readonly array $interceptors,
        string $host,
        int $port,
        int $mode,
        int $sockType,
        array $settings,
    ) {
        $this->server = new OpenSwooleHttpServer($host, $port, $mode, $sockType);
        $this->server->set(array_merge(
            [\OpenSwoole\Constant::OPTION_OPEN_HTTP2_PROTOCOL => 1],
            $settings,
        ));

        $dispatcher = new Dispatcher($this->container, $this->interceptors);

        $this->server->on('start', function (OpenSwooleHttpServer $server) use ($host, $port): void {
            \OpenSwoole\Util::LOG(\OpenSwoole\Constant::LOG_INFO, sprintf("\033[32m%s\033[0m", "OpenSwoole GRPC Server is started grpc://{$host}:{$port}"));
        });

        $this->server->on('workerStart', function (OpenSwooleHttpServer $server, int $workerId): void {
            $registry = new ServiceRegistry($this->container);

            foreach ($this->services as $serviceClass) {
                $registry->add($serviceClass);
            }

            $this->registry = $registry;
            $this->workerContext = new Context([
                OpenSwooleHttpServer::class => $server,
                ServiceRegistry::class => $registry,
            ]);
        });

        $this->server->on('request', function (\OpenSwoole\HTTP\Request $request, \OpenSwoole\HTTP\Response $response) use ($dispatcher): void {
            if ($this->registry === null) {
                $response->status(503);
                $response->end('gRPC worker not ready');

                return;
            }

            $dispatcher->dispatch($request, $response, $this->registry);
        });
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

    public function getOpenSwooleServer(): OpenSwooleHttpServer
    {
        return $this->server;
    }
}
