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
use TondbadSwoole\Console\Commands\HashCheckCommand;
use TondbadSwoole\Console\Commands\HashMakeCommand;
use TondbadSwoole\Console\Commands\MakeControllerCommand;
use TondbadSwoole\Console\Commands\MakeEventCommand;
use TondbadSwoole\Console\Commands\MakeGuardCommand;
use TondbadSwoole\Console\Commands\MakeListenerCommand;
use TondbadSwoole\Console\Commands\MakeMigrationCommand;
use TondbadSwoole\Console\Commands\MakeJobCommand;
use TondbadSwoole\Console\Commands\MakePolicyCommand;
use TondbadSwoole\Console\Commands\MakeRequestCommand;
use TondbadSwoole\Console\Commands\MakeMiddlewareCommand;
use TondbadSwoole\Console\Commands\MakeModelCommand;
use TondbadSwoole\Console\Commands\MakeProviderCommand;
use TondbadSwoole\Console\Commands\MigrateCommand;
use TondbadSwoole\Console\Commands\QueueWorkCommand;
use TondbadSwoole\Console\Commands\MigrateFreshCommand;
use TondbadSwoole\Console\Commands\MigrateRollbackCommand;
use TondbadSwoole\Console\Commands\MigrateStatusCommand;
use TondbadSwoole\Console\Commands\RouteCacheCommand;
use TondbadSwoole\Console\Commands\RouteListCommand;
use TondbadSwoole\Console\Commands\ScheduleListCommand;
use TondbadSwoole\Console\Commands\ScheduleWorkCommand;
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

            $this->registerBuiltInCommands($console, $basePath, $container);
            $this->registerConfiguredCommands($console, $container, $config, $basePath);
            $this->discoverCommands($console, $basePath, $container, $config);

            return $console;
        });
    }

    private function registerBuiltInCommands(Application $console, string $basePath, Container $container): void
    {
        $commands = [
            ServeCommand::class,
            GrpcServeCommand::class,
            RouteCacheCommand::class,
            RouteListCommand::class,
            CacheClearCommand::class,
            MakeControllerCommand::class,
            MakeEventCommand::class,
            MakeListenerCommand::class,
            MakeMiddlewareCommand::class,
            MakeModelCommand::class,
            MakeProviderCommand::class,
            MakeRequestCommand::class,
            MakeJobCommand::class,
            MakeGuardCommand::class,
            MakePolicyCommand::class,
            HashMakeCommand::class,
            HashCheckCommand::class,
            QueueWorkCommand::class,
            MakeMigrationCommand::class,
            MigrateCommand::class,
            MigrateFreshCommand::class,
            MigrateRollbackCommand::class,
            MigrateStatusCommand::class,
            ScheduleWorkCommand::class,
            ScheduleListCommand::class,
        ];

        foreach ($commands as $class) {
            $command = $this->resolveCommand($class, $basePath, $container);

            if ($command !== null) {
                $console->register($command);
            }
        }
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

    private function discoverCommands(Application $console, string $basePath, Container $container, Config $config): void
    {
        $commandsDir = $basePath . '/' . trim($config->get('app.paths.commands', 'app/Console/Commands'), '/');

        if (!is_dir($commandsDir)) {
            return;
        }

        foreach (glob($commandsDir . '/*.php') ?: [] as $file) {
            $class = $this->classNameFromFile($file, $config->get('app.namespaces.commands', 'App\\Console\\Commands\\'));

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

    private function classNameFromFile(string $file, string $namespace): ?string
    {
        $name = basename($file, '.php');

        return $namespace . $name;
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
