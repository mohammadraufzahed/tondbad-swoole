<?php

use OpenSwoole\GRPC\Middleware\{LoggingMiddleware, TraceMiddleware};
use TondbadSwoole\Core\GRPC\Middlewares\GrpcCorsMiddleware;

return [
    'services' => [
    ],
    'middlewares' => [
        LoggingMiddleware::class,
        TraceMiddleware::class,
        GrpcCorsMiddleware::class,
    ],
];
