<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;
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
    }
}
