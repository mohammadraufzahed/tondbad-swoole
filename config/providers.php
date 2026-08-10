<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
    CacheServiceProvider,
    ConsoleServiceProvider,
    HttpServiceProvider,
    LoggerServiceProvider,
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
    RouteServiceProvider::class,
    ConsoleServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
