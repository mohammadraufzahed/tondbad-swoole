<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => $env->get('AUTH_GUARD', 'token'),
        'provider' => $env->get('AUTH_PROVIDER', 'users'),
    ],

    'session' => [
        'store' => $env->get('AUTH_SESSION_STORE', 'database'),
    ],

    'access_token_ttl' => (int) $env->get('AUTH_ACCESS_TOKEN_TTL', 900),
    'refresh_token_ttl' => (int) $env->get('AUTH_REFRESH_TOKEN_TTL', 604800),

    'guards' => [
        'token' => [
            'driver' => 'token',
            'provider' => 'users',
            'storage_key' => 'api_token',
        ],

        'access_token' => [
            'driver' => 'access_token',
            'provider' => 'users',
            'mode' => 'stateful',
            'access_ttl' => (int) $env->get('AUTH_ACCESS_TOKEN_TTL', 900),
        ],

        'session' => [
            'driver' => 'session',
            'provider' => 'users',
            'session_key' => 'session_id',
            'mode' => 'stateful',
            'access_ttl' => (int) $env->get('AUTH_ACCESS_TOKEN_TTL', 900),
            'refresh_ttl' => (int) $env->get('AUTH_REFRESH_TOKEN_TTL', 604800),
            'cookie' => [
                'http_only' => true,
                'same_site' => 'lax',
                'secure' => (bool) $env->get('AUTH_COOKIE_SECURE', true),
                'path' => '/',
            ],
        ],

        'api_key' => [
            'driver' => 'api_key',
            'provider' => 'users',
            'storage_key' => 'api_key',
        ],

        'basic' => [
            'driver' => 'basic',
            'provider' => 'users',
            'username_key' => 'email',
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
