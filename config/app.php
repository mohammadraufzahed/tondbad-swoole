<?php

return [
    'name' => $env->get('app.name', 'Tondbad Framework'),
    'type' => $env->get('app.type', 'http'),
    'debug' => $env->get('app.debug', false),
    'middlewares' => [],
    'route_cache_file' => dirname(__DIR__) . '/storage/cache/routes.cache.php',
    'logging' => [
        'path' => $env->get('app.logging.path', dirname(__DIR__) . '/logs/app.log'),
        'level' => $env->get('app.logging.level', 'info'),
    ],
    'http' => [
        'port' => $env->get('app.http.port', 9501),
    ],
    'grpc' => [
        'port' => $env->get('app.grpc.port', 9502),
    ],
];
