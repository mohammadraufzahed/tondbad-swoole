<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Auth\Access\Gate;
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class GateServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Gate::class, function () use ($container): Gate {
            return new Gate(fn () => $container->make(AuthManager::class)->user());
        });
    }
}
