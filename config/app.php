<?php

use TondbadSwoole\Core\Env;

return [
    'name' => Env::get('app.name', 'Tondbad Framework'),
    'type' => Env::get('app.type', 'grpc'),
    'http' => [
        'port' => Env::get('app.http.port', 9501),
    ],
    'grpc' => [
        'port' => Env::get('app.grpc.port', 9502),
    ],
];
