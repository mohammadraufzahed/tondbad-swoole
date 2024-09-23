<?php

use TondbadSwoole\Providers\Default\{HttpServiceProvider, LoggerServiceProvider, RouteServiceProvider, GrpcServiceProvider, PredisCacheProvider, PhpRedisCacheProvider};

return [
    LoggerServiceProvider::class,
    PredisCacheProvider::class,
    PhpRedisCacheProvider::class,
    RouteServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class,
];