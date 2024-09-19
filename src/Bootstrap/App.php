<?php

namespace TondbadSwoole\Bootstrap;

use OpenSwoole\GRPC\Server as GrpcServer;
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class App
{
    private readonly Container $container;
    /**
     * @var ServiceProvider[]
     */
    private readonly array $providers;

    public function __construct()
    {
        $this->container = Container::create();
        $this->providers = Config::get('providers', []);
        $this->registerProviders();
        $this->bootProviders();
    }

    protected function registerProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass();
            $provider->beforeRegister($this->container);
            $provider->register($this->container);
            $provider->afterRegister($this->container);
        }
    }

    protected function bootProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass();
            $provider->beforeBoot($this->container);
            $provider->boot($this->container);
            $provider->afterBoot($this->container);
        }
    }

    public function run(): void
    {
        if (Config::get('app.type', 'http') === 'http') {
            $httpServer = $this->container->make(HttpServer::class);
            $httpServer->start();
        } else {
            $grpcServer = $this->container->make(GrpcServer::class);
            $grpcServer->start();
        }

    }
}
