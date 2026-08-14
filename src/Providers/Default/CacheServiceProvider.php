<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Contracts\CacheContract;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Cache\ChannelLock;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Cache\InMemoryTagManager;
use TondbadSwoole\Core\Cache\JsonSerializer;
use TondbadSwoole\Core\Cache\PhpRedisCache;
use TondbadSwoole\Core\Cache\PredisCache;
use TondbadSwoole\Core\Cache\RedisCache;
use TondbadSwoole\Core\Cache\RedisTagManager;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(CacheContract::class, function () use ($container) {
            $config = $container->make(Config::class);
            $driver = $config->get('cache.default', 'in-memory');

            $l1 = $this->createInMemoryCache($config);
            $l2 = $this->createL2($config, $driver, $container);
            $tagManager = $this->createTagManager($config, $driver, $container);
            $serializer = new JsonSerializer();
            $lock = new ChannelLock();

            return new HybridStore($l1, $l2, $serializer, $tagManager, $lock);
        });

        $container->singleton(CacheInterface::class, function () use ($container) {
            return $container->make(CacheContract::class);
        });
    }

    private function createInMemoryCache(Config $config): InMemoryCache
    {
        return new InMemoryCache(
            (int) $config->get('cache.in_memory.size', 1024),
            (int) $config->get('cache.in_memory.clean_interval', 1000)
        );
    }

    private function createL2(Config $config, string $driver, Container $container): ?CacheInterface
    {
        return match ($driver) {
            'predis', 'redis' => $container->make(RedisCache::class),
            'phpredis' => $container->make(PhpRedisCache::class),
            default => null,
        };
    }

    private function createTagManager(Config $config, string $driver, Container $container): ?\TondbadSwoole\Core\Cache\TagManager
    {
        return match ($driver) {
            'predis', 'redis' => new RedisTagManager($container->make(RedisCache::class)),
            default => new InMemoryTagManager(),
        };
    }
}
