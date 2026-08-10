<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Cache\PredisCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class PredisCacheProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(PredisCache::class, function () use ($container) {
            return new PredisCache($container->make(Config::class));
        });
    }
}
