<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use OpenSwoole\Atomic;
use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use TondbadSwoole\Contracts\Cache\CacheItem;
use TondbadSwoole\Core\Cache\ChannelLock;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Cache\JsonSerializer;
use TondbadSwoole\Core\Cache\RedisCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Env;

$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort = (int) (getenv('REDIS_PORT') ?: 6379);

$config = new Config(new Env());
$config->set('cache.redis', [
    'scheme' => 'tcp',
    'host' => $redisHost,
    'port' => $redisPort,
    'path' => null,
    'password' => null,
    'database' => 0,
    'timeout' => 5.0,
    'read_write_timeout' => null,
    'persistent' => false,
    'retry_interval' => 0,
    'options' => [
        'prefix' => 'tondbad_test_',
        'serializer' => 'php',
        'compression' => null,
    ],
]);

$results = [];
$callbackCount = new Atomic(0);

Coroutine::run(function () use ($config, &$results, $callbackCount): void {
    Runtime::enableCoroutine(SWOOLE_HOOK_TCP);

    $l2 = new RedisCache($config);
    $l1 = new InMemoryCache(128, 0);
    $lock = new ChannelLock();
    $cache = new HybridStore($l1, $l2, new JsonSerializer(), null, $lock, 5000);

    $cache->delete('concurrent-key');

    $done = new Coroutine\Channel(10);

    for ($i = 0; $i < 10; $i++) {
        Coroutine::create(function () use ($cache, $done, $callbackCount): void {
            $value = $cache->getOrSet('concurrent-key', function (CacheItem $item) use ($callbackCount) {
                $callbackCount->add(1);
                $item->lifetime(60);

                return 'computed';
            });

            $done->push($value);
        });
    }

    for ($i = 0; $i < 10; $i++) {
        $results[] = $done->pop();
    }

    $stats = $cache->stats();

    echo json_encode([
        'results' => $results,
        'callbackCount' => $callbackCount->get(),
        'loadCount' => $stats->loadCount,
        'hitCount' => $stats->hitCount,
        'missCount' => $stats->missCount,
    ]);
});
