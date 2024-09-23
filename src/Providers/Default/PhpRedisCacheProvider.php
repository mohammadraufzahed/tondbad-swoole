<?php

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Cache\PhpRedisCache;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class PhpRedisCacheProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(PhpRedisCache::class, fn() => new PhpRedisCache());
    }
}