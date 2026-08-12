<?php

declare(strict_types=1);

return [
    'default' => $env->get('HASH_DRIVER', 'bcrypt'),

    'drivers' => [
        'bcrypt' => [
            'rounds' => (int) $env->get('BCRYPT_ROUNDS', '10'),
        ],

        'argon2id' => [
            'memory_cost' => (int) $env->get('ARGON_MEMORY', (string) PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
            'time_cost' => (int) $env->get('ARGON_TIME', (string) PASSWORD_ARGON2_DEFAULT_TIME_COST),
            'threads' => (int) $env->get('ARGON_THREADS', (string) PASSWORD_ARGON2_DEFAULT_THREADS),
        ],
    ],
];
