<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use ReflectionClass;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\CommandInterface;
use TondbadSwoole\Console\Commands\CacheClearCommand;
use TondbadSwoole\Console\Commands\GrpcServeCommand;
use TondbadSwoole\Console\Commands\MakeControllerCommand;
use TondbadSwoole\Console\Commands\MakeMiddlewareCommand;
use TondbadSwoole\Console\Commands\MakeProviderCommand;
use TondbadSwoole\Console\Commands\RouteCacheCommand;
use TondbadSwoole\Console\Commands\ServeCommand;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Application::class, function () use ($container) {
            $app = $container->make(App::class);
            $config = $container->make(Config::class);
            $basePath = $app->basePath();

            $console = new Application($basePath);

            $this->registerBuiltInCommands($console, $basePath);
            $this->registerConfiguredCommands($console, $container, $config);
            $this->discoverCommands($console, $basePath, $container);

            return $console;
        });
    }

    private function registerBuiltInCommands(Application $console, string $basePath): void
    {
        $console
            ->register(new ServeCommand($basePath))
            ->register(new GrpcServeCommand($basePath))
            ->register(new RouteCacheCommand($basePath))
            ->register(new CacheClearCommand($basePath))
            ->register(new MakeControllerCommand($basePath))
            ->register(new MakeMiddlewareCommand($basePath))
            ->register(new MakeProviderCommand($basePath));
    }

    private function registerConfiguredCommands(Application $console, Container $container, Config $config): void
    {
        $commands = $config->get('app.commands', []);

        foreach ($commands as $commandClass) {
            if (!is_string($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
                continue;
            }

            $console->register($container->make($commandClass));
        }
    }

    private function discoverCommands(Application $console, string $basePath, Container $container): void
    {
        $commandsDir = $basePath . '/app/Console/Commands';

        if (!is_dir($commandsDir)) {
            return;
        }

        foreach (glob($commandsDir . '/*.php') ?: [] as $file) {
            $class = $this->classNameFromFile($file);

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (!$reflection->implementsInterface(CommandInterface::class) || $reflection->isAbstract()) {
                continue;
            }

            $console->register($container->make($class));
        }
    }

    private function classNameFromFile(string $file): ?string
    {
        $name = basename($file, '.php');

        return 'App\\Console\\Commands\\' . $name;
    }
}
