<?php

use TondbadSwoole\Providers\Default\{HttpServiceProvider, LoggerServiceProvider, RouteServiceProvider, GrpcServiceProvider};

return [
    LoggerServiceProvider::class,
    RouteServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class
];