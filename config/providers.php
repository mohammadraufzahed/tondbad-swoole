<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
    CacheServiceProvider,
    ConsoleServiceProvider,
    DatabaseServiceProvider,
    GrpcServiceProvider,
    HttpServiceProvider,
    LoggerServiceProvider,
    PhpRedisCacheProvider,
    PredisCacheProvider,
    QueueServiceProvider,
    RouteServiceProvider,
    ScheduleServiceProvider,
};

return [
    LoggerServiceProvider::class,
    PredisCacheProvider::class,
    PhpRedisCacheProvider::class,
    CacheServiceProvider::class,
    DatabaseServiceProvider::class,
    QueueServiceProvider::class,
    RouteServiceProvider::class,
    ScheduleServiceProvider::class,
    ConsoleServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
