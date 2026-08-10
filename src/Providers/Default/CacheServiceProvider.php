<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Cache\PhpRedisCache;
use TondbadSwoole\Core\Cache\PredisCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(CacheInterface::class, function () use ($container) {
            $config = $container->make(Config::class);
            $driver = $config->get('cache.default', 'in-memory');

            return match ($driver) {
                'predis' => $container->make(PredisCache::class),
                'phpredis' => $container->make(PhpRedisCache::class),
                default => $this->createInMemoryCache($config),
            };
        });
    }

    private function createInMemoryCache(Config $config): InMemoryCache
    {
        return new InMemoryCache(
            (int) $config->get('cache.in_memory.size', 1024),
            (int) $config->get('cache.in_memory.clean_interval', 1000)
        );
    }
}
