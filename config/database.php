<?php

declare(strict_types=1);

return [
    'default' => $env->get('db.connection', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $env->get('db.mysql.host', '127.0.0.1'),
            'port' => (int) $env->get('db.mysql.port', 3306),
            'database' => $env->get('db.mysql.database', 'tondbad'),
            'username' => $env->get('db.mysql.username', 'root'),
            'password' => $env->get('db.mysql.password', ''),
            'charset' => $env->get('db.mysql.charset', 'utf8mb4'),
            'prefix' => $env->get('db.mysql.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => (int) $env->get('db.mysql.pool.min', 1),
                'max' => (int) $env->get('db.mysql.pool.max', 10),
                'wait_timeout' => (float) $env->get('db.mysql.pool.wait_timeout', 3.0),
            ],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $env->get('db.sqlite.database', ':memory:'),
            'prefix' => $env->get('db.sqlite.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => 1,
                'max' => 1,
                'wait_timeout' => 3.0,
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $env->get('db.pgsql.host', '127.0.0.1'),
            'port' => (int) $env->get('db.pgsql.port', 5432),
            'database' => $env->get('db.pgsql.database', 'tondbad'),
            'username' => $env->get('db.pgsql.username', 'postgres'),
            'password' => $env->get('db.pgsql.password', ''),
            'charset' => $env->get('db.pgsql.charset', 'utf8'),
            'prefix' => $env->get('db.pgsql.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => (int) $env->get('db.pgsql.pool.min', 1),
                'max' => (int) $env->get('db.pgsql.pool.max', 10),
                'wait_timeout' => (float) $env->get('db.pgsql.pool.wait_timeout', 3.0),
            ],
        ],
    ],
];
