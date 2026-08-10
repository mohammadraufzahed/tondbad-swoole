<?php

declare(strict_types=1);

namespace TondbadSwoole\Bootstrap;

use Exception;
use Monolog\Logger;
use OpenSwoole\GRPC\Server as GrpcServer;
use OpenSwoole\WebSocket\Server as HttpServer;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class App
{
    public readonly Container $container;
    public readonly Env $env;
    public readonly Config $config;

    /**
     * @var ServiceProvider[]
     */
    private readonly array $providers;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->env = new Env();
        $this->env->loadAll();

        $this->config = new Config($this->env, [dirname(__DIR__, 2) . '/config']);

        $this->container = new Container();
        $this->container->singleton(Container::class, fn() => $this->container);
        $this->container->singleton(Env::class, fn() => $this->env);
        $this->container->singleton(Config::class, fn() => $this->config);

        $this->providers = $this->loadProviders();
        $this->registerProviders();
    }

    public function routes(): Route
    {
        return $this->container->make(Route::class);
    }

    /**
     * @return ServiceProvider[]
     * @throws Exception
     */
    private function loadProviders(): array
    {
        $providers = array_map(
            fn(string $provider) => $this->container->make($provider),
            $this->config->get('providers', [])
        );

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

    protected function registerProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->beforeRegister($this->container);
            $provider->register($this->container);
            $provider->afterRegister($this->container);
        }
    }

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

        $server = $this->config->get('app.type', 'http') === 'http'
            ? $this->container->make(HttpServer::class)
            : $this->container->make(GrpcServer::class);

        $this->registerShutdownHandlers($server);

        $server->start();
    }

    private function registerShutdownHandlers(object $server): void
    {
        $server->on('Shutdown', function () {
            $this->container->make(Logger::class)?->info('Server shutting down gracefully.');
        });

        if (function_exists('pcntl_signal') && method_exists($server, 'shutdown')) {
            $sigterm = defined('SIGTERM') ? SIGTERM : 15;
            $sigint = defined('SIGINT') ? SIGINT : 2;

            pcntl_signal($sigterm, fn() => $server->shutdown());
            pcntl_signal($sigint, fn() => $server->shutdown());
        }
    }
}
