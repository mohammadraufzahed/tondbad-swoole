<?php

namespace TondbadSwoole\Bootstrap;

use Exception;
use OpenSwoole\GRPC\Server as GrpcServer;
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class App
{
    /**
     * The application container instance used for dependency resolution and service management.
     *
     * @var Container
     */
    private readonly Container $container;

    /**
     * An array of service provider class names that will be registered and booted.
     * The list of providers is fetched from the application configuration.
     *
     * @var ServiceProvider[]
     */
    private readonly array $providers;

    /**
     * App constructor.
     *
     * Initializes the application by creating the container instance and loading
     * the service providers from the configuration. After loading the providers,
     * it registers and boots them.
     * @throws Exception
     */
    public function __construct()
    {
        $this->container = Container::create();
        $this->providers = $this->loadProviders();
        $this->registerProviders();
        $this->bootProviders();
    }

    /**
     * Load and sort service providers based on their priority.
     *
     * This method retrieves the list of service provider class names from the configuration,
     * creates instances of these providers using the container, and sorts them in ascending
     * order of their priority values. Providers with a lower priority value will be registered
     * and booted before those with a higher value.
     *
     * @return ServiceProvider[] The sorted array of service provider instances.
     * @throws Exception
     */
    private function loadProviders(): array
    {
        $providers = array_map(fn(string $provider) => $this->container->make($provider), Config::get('providers', []));
        usort($providers, fn(ServiceProvider $a, ServiceProvider $b) => $a->getPriority() <=> $b->getPriority());
        return $providers;
    }

    /**
     * Register all service providers in the container.
     *
     * This method iterates over the list of service providers, instantiates each one,
     * and calls the `beforeRegister`, `register`, and `afterRegister` methods on each provider.
     * This allows providers to bind services and perform setup tasks during registration.
     *
     * @return void
     */
    protected function registerProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->beforeRegister($this->container);
            $provider->register($this->container);
            $provider->afterRegister($this->container);
        }
    }

    /**
     * Boot all registered service providers.
     *
     * This method iterates over the list of service providers, instantiates each one,
     * and calls the `beforeBoot`, `boot`, and `afterBoot` methods on each provider.
     * This allows providers to perform any necessary actions after all services have been registered.
     *
     * @return void
     */
    protected function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
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
