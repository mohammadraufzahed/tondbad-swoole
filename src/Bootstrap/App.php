<?php

namespace TondbadSwoole\Bootstrap;

use Exception;
use Monolog\Logger;
use OpenSwoole\GRPC\Server as GrpcServer;
use OpenSwoole\Process;
use OpenSwoole\Server;
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
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
        Env::loadAll();

        $this->container = Container::create();
        $this->container->singleton(Container::class, fn() => $this->container);

        $this->providers = $this->loadProviders();
        $this->registerProviders();
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

        // Sort by priority (ascending) while preserving the original order for ties.
        $indexedProviders = array_map(
            fn(ServiceProvider $provider, int $index) => ['provider' => $provider, 'index' => $index],
            $providers,
            array_keys($providers)
        );

        usort($indexedProviders, function (array $a, array $b) {
            $priorityComparison = $a['provider']->getPriority() <=> $b['provider']->getPriority();
            return $priorityComparison === 0 ? $a['index'] <=> $b['index'] : $priorityComparison;
        });

        return array_map(fn(array $item) => $item['provider'], $indexedProviders);
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
        $this->bootProviders();

        $server = Config::get('app.type', 'http') === 'http'
            ? $this->container->make(HttpServer::class)
            : $this->container->make(GrpcServer::class);

        $this->registerShutdownHandlers($server);

        $server->start();
    }

    private function registerShutdownHandlers(Server $server): void
    {
        $server->on('Shutdown', function () {
            $this->container->make(Logger::class)?->info('Server shutting down gracefully.');
        });

        $sigterm = defined('SIGTERM') ? SIGTERM : 15;
        $sigint = defined('SIGINT') ? SIGINT : 2;

        Process::signal($sigterm, fn() => $server->shutdown());
        Process::signal($sigint, fn() => $server->shutdown());
    }
}
