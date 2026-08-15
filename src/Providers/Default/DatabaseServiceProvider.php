<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\EntityManager;
use TondbadSwoole\Database\EntityManagerInterface;
use TondbadSwoole\Database\Migrations\MigrationCreator;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Database\Migrations\MigrationPathManager;
use TondbadSwoole\Database\Migrations\MigrationRepository;
use TondbadSwoole\Database\Migrations\Migrator;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(DatabaseManager::class, function () use ($container): DatabaseManager {
            return new DatabaseManager(
                $container->make(Config::class),
                $container->make(ContextInterface::class),
            );
        });

        $container->singleton(ConnectionInterface::class, function () use ($container): ConnectionInterface {
            return $container->make(DatabaseManager::class)->connection();
        });

        $container->singleton(MigrationRepository::class, function () use ($container): MigrationRepository {
            return new MigrationRepository($container->make(ConnectionInterface::class));
        });

        $container->singleton(MigrationPathManager::class, function () use ($container): MigrationPathManager {
            $app = $container->make(App::class);
            $config = $container->make(Config::class);
            $configured = $config->get('database.migrations', 'database/migrations');
            $paths = is_array($configured) ? $configured : [$configured];

            $manager = new MigrationPathManager();

            foreach ($paths as $path) {
                $manager->addPath($app->basePath($path));
            }

            return $manager;
        });

        $container->singleton(Migrator::class, function () use ($container): Migrator {
            return new Migrator(
                $container->make(MigrationRepository::class),
                $container->make(ConnectionInterface::class),
                $container->make(MigrationPathManager::class),
            );
        });

        $container->singleton(MigrationCreator::class, fn () => new MigrationCreator());

        $container->bind(EntityManagerInterface::class, function () use ($container): EntityManagerInterface {
            $context = $container->make(ContextInterface::class);
            $key = EntityManagerInterface::class;
            $entityManager = $context->get($key);

            if (!$entityManager instanceof EntityManagerInterface) {
                $dispatcher = $container->has(EventDispatcher::class) ? $container->make(EventDispatcher::class) : null;
                $entityManager = new EntityManager($container->make(ConnectionInterface::class), $dispatcher);
                $context->set($key, $entityManager);
            }

            return $entityManager;
        });
    }

    public function boot(Container $container): void
    {
        $manager = $container->make(MigrationPathManager::class);

        foreach ($container->make(App::class)->getProviders() as $provider) {
            foreach ($provider->getMigrationPaths() as $path) {
                $manager->addPath($path);
            }
        }
    }
}
