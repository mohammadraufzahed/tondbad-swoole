<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Support\Hash\Contracts\Hasher;
use TondbadSwoole\Support\Hash\HashManager;

class HashServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(HashManager::class, function () use ($container): HashManager {
            return new HashManager($container->make(Config::class));
        });

        $container->singleton(Hasher::class, function () use ($container): Hasher {
            return $container->make(HashManager::class)->driver();
        });
    }
}
