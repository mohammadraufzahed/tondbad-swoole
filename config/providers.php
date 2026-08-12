<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
    CacheServiceProvider,
    ConsoleServiceProvider,
    DatabaseServiceProvider,
    EventServiceProvider,
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
    EventServiceProvider::class,
    RouteServiceProvider::class,
    ScheduleServiceProvider::class,
    ConsoleServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
