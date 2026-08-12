<?php

declare(strict_types=1);

namespace TondbadSwoole\Bootstrap;

use Exception;
use Monolog\Logger;
use OpenSwoole\GRPC\Server as GrpcServer;
use OpenSwoole\Http\Server as HttpServer;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Core\Exceptions\ConfigurationException;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Support\Context;

class App
{
    private static ?self $instance = null;

    public readonly Container $container;
    public readonly Env $env;
    public readonly Config $config;

    /**
     * @var ServiceProvider[]
     */
    private readonly array $providers;

    private bool $booted = false;

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * @throws Exception
     */
    public function __construct(private readonly string $basePath)
    {
        self::$instance = $this;
        $this->env = new Env();
        $this->env->loadAll([$this->basePath]);

        $this->config = new Config($this->env, $this->basePath, [$this->basePath . '/config']);
        $this->validateConfiguration();

        $this->container = new Container();
        $this->container->singleton(Container::class, fn() => $this->container);
        $this->container->singleton(Env::class, fn() => $this->env);
        $this->container->singleton(Config::class, fn() => $this->config);
        $this->container->singleton(ContextInterface::class, fn() => new Context());
        $this->container->singleton(App::class, fn() => $this);

        $this->providers = $this->loadProviders();
        $this->registerProviders();
    }

    public function basePath(string $path = ''): string
    {
        return $path === '' ? $this->basePath : $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * @throws ConfigurationException
     */
    private function validateConfiguration(): void
    {
        $rules = [
            'app.name' => 'string',
            'app.type' => 'string',
            'app.debug' => 'bool',
            'app.logging.path' => 'string',
            'app.http.host' => 'string',
            'app.http.port' => 'int',
            'app.grpc.host' => 'string',
            'app.grpc.port' => 'int',
            'app.middlewares' => 'array',
        ];

        foreach ($rules as $key => $expected) {
            $value = $this->config->get($key);
            $valid = match ($expected) {
                'string' => is_string($value) && $value !== '',
                'bool' => is_bool($value),
                'int' => is_int($value) && $value > 0 && $value <= 65535,
                'array' => is_array($value),
                default => true,
            };

            if (!$valid) {
                throw new ConfigurationException("Configuration value '{$key}' must be a non-empty {$expected}.");
            }
        }

        $appType = $this->config->get('app.type');
        if (!in_array($appType, ['http', 'grpc'], true)) {
            throw new ConfigurationException("Configuration 'app.type' must be 'http' or 'grpc', '{$appType}' given.");
        }
    }

    public function routes(): Route
    {
        return $this->container->make(Route::class);
    }

    /**
     * @return ServiceProvider[]
     */
    public function getProviders(): array
    {
        return $this->providers;
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

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->providers as $provider) {
            $provider->beforeBoot($this->container);
            $provider->boot($this->container);
            $provider->afterBoot($this->container);
        }

        $this->booted = true;

        return $this;
    }

    public function run(): void
    {
        $this->boot();

        $server = $this->config->get('app.type', 'http') === 'http'
            ? $this->container->make(HttpServer::class)
            : $this->container->make(GrpcServer::class);

        $this->registerShutdownHandlers($server);

        $server->start();
    }

    private function registerShutdownHandlers(object $server): void
    {
        $container = $this->container;

        $server->on('Shutdown', function () use ($container) {
            $container->make(Logger::class)?->info('Server shutting down gracefully.');
        });

        if (function_exists('pcntl_signal') && method_exists($server, 'shutdown')) {
            $sigterm = defined('SIGTERM') ? SIGTERM : 15;
            $sigint = defined('SIGINT') ? SIGINT : 2;

            pcntl_signal($sigterm, fn() => $server->shutdown());
            pcntl_signal($sigint, fn() => $server->shutdown());
        }
    }
}
