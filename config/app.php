<?php
use TondbadSwoole\Core\Env;

return [
    'name' => Env::get('app.name', 'Tondbad Framework'),
    'type' => Env::get('app.type', 'http'),
    'http' => [
        'port' => Env::get('app.http.port', 9501),
    ],
    'grpc' => [
        'port' => Env::get('app.grpc.port', 9502),
        'services' => [
        ],
    ],
];