<?php

use TondbadSwoole\Core\Env;

return [
    'name' => Env::get('app.name', 'Tondbad Framework'),
    'type' => Env::get('app.type', 'http'),
    'debug' => Env::get('app.debug', false),
    'logging' => [
        'path' => Env::get('app.logging.path', dirname(__DIR__) . '/logs/app.log'),
        'level' => Env::get('app.logging.level', 'info'),
    ],
    'http' => [
        'port' => Env::get('app.http.port', 9501),
    ],
    'grpc' => [
        'port' => Env::get('app.grpc.port', 9502),
    ],
];
