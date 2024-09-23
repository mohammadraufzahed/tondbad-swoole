<?php

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Cache\PredisCache;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class PredisCacheProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(PredisCache::class, fn() => new PredisCache());
    }
}