<?php

return [
    'name' => 'Tondbad Framework',
    'type' => 'http',
    'http' => [
        'port' => 9501,
    ],
    'grpc' => [
        'port' => 9502,
        'services' => [
            TondbadExample\GreetingServiceService::class
        ],
    ],
];