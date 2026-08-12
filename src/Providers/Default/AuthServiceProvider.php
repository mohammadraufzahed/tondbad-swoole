<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(AuthManager::class, function () use ($container): AuthManager {
            return new AuthManager(
                $container,
                $container->make(\TondbadSwoole\Core\Config::class),
            );
        });

        $container->bind(Guard::class, function () use ($container): Guard {
            return $container->make(AuthManager::class)->guard();
        });
    }
}
