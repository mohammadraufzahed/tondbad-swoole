<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Cache\PhpRedisCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class PhpRedisCacheProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(PhpRedisCache::class, function () use ($container) {
            return new PhpRedisCache($container->make(Config::class));
        });
    }
}
