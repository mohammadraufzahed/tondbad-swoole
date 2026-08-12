<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
    AuthServiceProvider,
    CacheServiceProvider,
    ConsoleServiceProvider,
    DatabaseServiceProvider,
    EventServiceProvider,
    GateServiceProvider,
    GrpcServiceProvider,
    HashServiceProvider,
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
    HashServiceProvider::class,
    QueueServiceProvider::class,
    EventServiceProvider::class,
    AuthServiceProvider::class,
    GateServiceProvider::class,
    RouteServiceProvider::class,
    ScheduleServiceProvider::class,
    ConsoleServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
