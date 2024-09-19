<?php
use TondbadSwoole\Providers\Default\{
    GrpcServiceProvider,
    HttpServiceProvider,
    LoggerServiceProvider
};

return [
    LoggerServiceProvider::class,
    HttpServiceProvider::class,
    GrpcServiceProvider::class
];