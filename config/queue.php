<?php

declare(strict_types=1);

return [
    'default' => $env->get('queue.default', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => $env->get('queue.database.connection', null),
            'table' => $env->get('queue.database.table', 'jobs'),
            'queue' => $env->get('queue.database.queue', 'default'),
            'retry_after' => (int) $env->get('queue.database.retry_after', 60),
            'pause_table' => $env->get('queue.database.pause_table', 'queue_pauses'),
        ],

        'redis' => [
            'driver' => 'redis',
            'scheme' => $env->get('queue.redis.scheme', 'tcp'),
            'host' => $env->get('queue.redis.host', '127.0.0.1'),
            'port' => (int) $env->get('queue.redis.port', 6379),
            'password' => $env->get('queue.redis.password', null),
            'database' => (int) $env->get('queue.redis.database', 0),
            'prefix' => $env->get('queue.redis.prefix', 'tondbad'),
            'queue' => $env->get('queue.redis.queue', 'default'),
            'retry_after' => (int) $env->get('queue.redis.retry_after', 60),
            'block_for' => (int) $env->get('queue.redis.block_for', 1),
        ],
    ],

    'failed' => [
        'driver' => $env->get('queue.failed.driver', 'database'),
        'database' => $env->get('queue.failed.database', null),
        'table' => $env->get('queue.failed.table', 'failed_jobs'),
    ],

    'rateLimiter' => [
        'driver' => $env->get('queue.rateLimiter.driver', null),
        'database' => $env->get('queue.rateLimiter.database', null),
        'table' => $env->get('queue.rateLimiter.table', 'rate_limits'),
    ],
];
