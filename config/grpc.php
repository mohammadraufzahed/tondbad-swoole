<?php

declare(strict_types=1);

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
