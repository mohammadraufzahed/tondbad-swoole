<?php

declare(strict_types=1);

return [
    'name' => $env->get('app.name', 'Tondbad Framework'),
    'type' => $env->get('app.type', 'http'),
    'debug' => (bool) $env->get('app.debug', false),
    'middlewares' => [],
    'commands' => [],
    'route_cache_file' => $env->get('app.route_cache_file', $basePath . '/storage/cache/routes.cache.php'),
    'logging' => [
        'path' => $env->get('app.logging.path', $basePath . '/storage/logs/app.log'),
        'level' => $env->get('app.logging.level', 'info'),
    ],
    'http' => [
        'host' => $env->get('app.http.host', '0.0.0.0'),
        'port' => $env->get('app.http.port', 9501),
    ],
    'grpc' => [
        'host' => $env->get('app.grpc.host', '0.0.0.0'),
        'port' => $env->get('app.grpc.port', 9502),
    ],
];
