<?php

use OpenSwoole\GRPC\Middleware\{TraceMiddleware, LoggingMiddleware};
use TondbadSwoole\Core\GRPC\Middlewares\CorsMiddleware;
use TondbadSwoole\Services\GreetingGRPCService;

return [
    'services' => [
        GreetingGRPCService::class
    ],
    'middlewares' => [
        LoggingMiddleware::class,
        TraceMiddleware::class,
        CorsMiddleware::class,
    ]
];
