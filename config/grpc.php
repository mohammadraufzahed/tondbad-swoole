<?php

use OpenSwoole\GRPC\Middleware\{TraceMiddleware, LoggingMiddleware};
use TondbadSwoole\Core\GRPC\Middlewares\CorsMiddleware;

return [
    'services' => [
    ],
    'middlewares' => [
        LoggingMiddleware::class,
        TraceMiddleware::class,
        CorsMiddleware::class,
    ]
];
