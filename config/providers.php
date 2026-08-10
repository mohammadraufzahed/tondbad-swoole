<?php

declare(strict_types=1);

use TondbadSwoole\Providers\Default\{
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
    RouteServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];
