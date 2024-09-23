<?php

use OpenSwoole\Coroutine;
use TondbadSwoole\Core\Cache\PredisCache;

require __DIR__ . '/../vendor/autoload.php';

Coroutine::run(function () {
    $cache = new PredisCache();
    if ($cache->set('Hello', 'World', 5))
        echo "Cache set\n";
    else
        echo "Cache not set\n";

    echo 'The cache value is: ' . $cache->get('Hello') . "\n";
    sleep(10);

    if ($cache->has("Hello"))
        echo "Cache Exists\n";
    else
        echo "Cache deleted\n";
});