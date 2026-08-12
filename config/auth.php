<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => $env->get('AUTH_GUARD', 'token'),
        'provider' => $env->get('AUTH_PROVIDER', 'users'),
    ],

    'guards' => [
        'token' => [
            'driver' => 'token',
            'provider' => 'users',
            'storage_key' => 'api_token',
        ],

        'session' => [
            'driver' => 'session',
            'provider' => 'users',
            'session_key' => 'session_id',
            'lifetime' => 7200,
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => 'users',
            'auth_identifier' => 'id',
            'auth_password' => 'password',
        ],
    ],
];
