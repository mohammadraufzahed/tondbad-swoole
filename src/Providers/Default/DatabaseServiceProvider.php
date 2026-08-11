<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\Migrations\MigrationCreator;
use TondbadSwoole\Database\Migrations\MigrationRepository;
use TondbadSwoole\Database\Migrations\Migrator;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(DatabaseManager::class, function () use ($container): DatabaseManager {
            return new DatabaseManager($container->make(Config::class));
        });

        $container->singleton(ConnectionInterface::class, function () use ($container): ConnectionInterface {
            return $container->make(DatabaseManager::class)->connection();
        });

        $container->singleton(MigrationRepository::class, function () use ($container): MigrationRepository {
            return new MigrationRepository($container->make(ConnectionInterface::class));
        });

        $container->singleton(Migrator::class, function () use ($container): Migrator {
            $app = $container->make(App::class);
            $config = $container->make(Config::class);
            $path = $app->basePath($config->get('database.migrations', 'database/migrations'));

            return new Migrator(
                $container->make(MigrationRepository::class),
                $container->make(ConnectionInterface::class),
                $path,
            );
        });

        $container->singleton(MigrationCreator::class, fn () => new MigrationCreator());
    }
}
