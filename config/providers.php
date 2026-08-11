<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
    CacheServiceProvider,
    ConsoleServiceProvider,
    DatabaseServiceProvider,
    HttpServiceProvider,
    LoggerServiceProvider,
    QueueServiceProvider,
    RouteServiceProvider,
    GrpcServiceProvider,
    PredisCacheProvider,
    PhpRedisCacheProvider
};

return [
    LoggerServiceProvider::class,
    PredisCacheProvider::class,
    PhpRedisCacheProvider::class,
    CacheServiceProvider::class,
    DatabaseServiceProvider::class,
    QueueServiceProvider::class,
    RouteServiceProvider::class,
    ConsoleServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
