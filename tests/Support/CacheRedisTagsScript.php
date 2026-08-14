<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use TondbadSwoole\Contracts\Cache\CacheItem;
use TondbadSwoole\Core\Cache\ChannelLock;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Cache\JsonSerializer;
use TondbadSwoole\Core\Cache\RedisCache;
use TondbadSwoole\Core\Cache\RedisTagManager;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Env;

$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort = (int) (getenv('REDIS_PORT') ?: 6379);

$config = new Config(new Env());
$config->set('cache.redis', [
    'scheme' => 'tcp',
    'host' => $redisHost,
    'port' => $redisPort,
    'password' => null,
    'database' => 0,
    'timeout' => 5.0,
    'pool' => ['size' => 2],
    'options' => ['prefix' => 'tondbad_test_'],
]);

$result = [];

Coroutine::run(function () use ($config, &$result): void {
    Runtime::enableCoroutine(SWOOLE_HOOK_TCP);

    $l2 = new RedisCache($config);
    $tagManager = new RedisTagManager($l2, 'tondbad_test_tag:');
    $l1 = new InMemoryCache(128, 0);
    $cache = new HybridStore($l1, $l2, new JsonSerializer(), $tagManager, new ChannelLock(), 5000);

    $cache->clear();

    $users = $cache->getOrSet('users:1', function (CacheItem $item) {
        $item->lifetime(60)->tag('users');

        return 'ava';
    });

    $orders = $cache->getOrSet('orders:2', function (CacheItem $item) {
        $item->lifetime(60)->tag('orders');

        return 'order-data';
    });

    $cache->invalidateTags(['users']);

    $usersAfter = $cache->get('users:1');
    $ordersAfter = $cache->get('orders:2');

    $result = [
        'usersBefore' => $users,
        'ordersBefore' => $orders,
        'usersAfter' => $usersAfter,
        'ordersAfter' => $ordersAfter,
    ];

    echo json_encode($result);
});
