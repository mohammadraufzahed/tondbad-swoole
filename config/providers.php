<?php

use TondbadSwoole\Providers\Default\{
    HttpServiceProvider,
    LoggerServiceProvider,
    RouteServiceProvider,
    GrpcServiceProvider,
    PredisCacheProvider,
    PhpRedisCacheProvider,
    EnvServiceProvider
};

return [
    LoggerServiceProvider::class,
    PredisCacheProvider::class,
    PhpRedisCacheProvider::class,
    RouteServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
    EnvServiceProvider::class,
];