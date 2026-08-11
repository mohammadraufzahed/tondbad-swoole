<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use ReflectionClass;
use ReflectionNamedType;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\CommandInterface;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Console\Commands\CacheClearCommand;
use TondbadSwoole\Console\Commands\GrpcServeCommand;
use TondbadSwoole\Console\Commands\MakeControllerCommand;
use TondbadSwoole\Console\Commands\MakeMigrationCommand;
use TondbadSwoole\Console\Commands\MakeMiddlewareCommand;
use TondbadSwoole\Console\Commands\MakeProviderCommand;
use TondbadSwoole\Console\Commands\MigrateCommand;
use TondbadSwoole\Console\Commands\MigrateFreshCommand;
use TondbadSwoole\Console\Commands\MigrateRollbackCommand;
use TondbadSwoole\Console\Commands\MigrateStatusCommand;
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
            $this->registerConfiguredCommands($console, $container, $config, $basePath);
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
            ->register(new MakeProviderCommand($basePath))
            ->register(new MakeMigrationCommand($basePath))
            ->register(new MigrateCommand($basePath))
            ->register(new MigrateFreshCommand($basePath))
            ->register(new MigrateRollbackCommand($basePath))
            ->register(new MigrateStatusCommand($basePath));
    }

    private function registerConfiguredCommands(Application $console, Container $container, Config $config, string $basePath): void
    {
        $commands = $config->get('app.commands', []);

        foreach ($commands as $commandClass) {
            if (!is_string($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
                continue;
            }

            $command = $this->resolveCommand($commandClass, $basePath, $container);

            if ($command !== null) {
                $console->register($command);
            }
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

            $command = $this->resolveCommand($class, $basePath, $container);

            if ($command !== null) {
                $console->register($command);
            }
        }
    }

    private function classNameFromFile(string $file): ?string
    {
        $name = basename($file, '.php');

        return 'App\\Console\\Commands\\' . $name;
    }

    private function resolveCommand(string $class, string $basePath, Container $container): ?CommandInterface
    {
        if (!class_exists($class) || !is_subclass_of($class, CommandInterface::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $container->make($class);
        }

        $parameters = $constructor->getParameters();

        if (count($parameters) === 1
            && $parameters[0]->getName() === 'basePath'
            && ($parameters[0]->getType() instanceof ReflectionNamedType)
            && ($parameters[0]->getType()->getName() === 'string')
        ) {
            return new $class($basePath);
        }

        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($parameter->getName() === 'basePath'
                && ($type instanceof ReflectionNamedType)
                && ($type->getName() === 'string')
            ) {
                $dependencies[] = $basePath;

                continue;
            }

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $dependencies[] = $container->make($type->getName());

                    continue;
                } catch (\Throwable $e) {
                    // fall through to defaults/null handling below
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $dependencies[] = null;
            } else {
                return null;
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
