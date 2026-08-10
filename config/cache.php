<?php

declare(strict_types=1);

return [
    'default' => $env->get('cache.default', 'in-memory'),
    'in_memory' => [
        'size' => (int) $env->get('cache.in_memory.size', 1024),
        'clean_interval' => (int) $env->get('cache.in_memory.clean_interval', 1000),
    ],
    'redis' => [
        'scheme' => $env->get('redis.scheme', 'tcp'),
        'host' => $env->get('redis.host', '127.0.0.1'),
        'port' => $env->get('redis.port', 6379),
        'path' => $env->get('redis.path', null),
        'password' => $env->get('redis.password', null),
        'database' => $env->get('redis.database', 0),
        'timeout' => $env->get('redis.timeout', 5.0),
        'read_write_timeout' => $env->get('redis.read_write_timeout', null),
        'persistent' => $env->get('redis.persistent', false),
        'retry_interval' => $env->get('redis.retry_interval', 0),

        'ssl' => [
            'enabled' => $env->get('redis.ssl.enabled', false),
            'cafile' => $env->get('redis.ssl.cafile', null),
            'verify_peer' => $env->get('redis.ssl.verify_peer', true),
            'verify_peer_name' => $env->get('redis.ssl.verify_peer_name', true),
        ],

        'cluster' => [
            'enabled' => $env->get('redis.cluster.enabled', false),
            'nodes' => [
                $env->get('redis.cluster.node_1', '127.0.0.1:6379'),
                $env->get('redis.cluster.node_2', null),
                $env->get('redis.cluster.node_3', null),
            ],
        ],

        'sentinel' => [
            'enabled' => $env->get('redis.sentinel.enabled', false),
            'service' => $env->get('redis.sentinel.service', 'mymaster'),
            'nodes' => [
                $env->get('redis.sentinel.node_1', '127.0.0.1:26379'),
                $env->get('redis.sentinel.node_2', null),
                $env->get('redis.sentinel.node_3', null),
            ],
        ],

        'options' => [
            'prefix' => $env->get('redis.options.prefix', ''),
            'serializer' => $env->get('redis.options.serializer', 'php'),
            'compression' => $env->get('redis.options.compression', null),
        ],
    ],
    'memcached' => [
        'host' => $env->get('memcached.host', '127.0.0.1'),
        'port' => (int) $env->get('memcached.port', 11211),
        'username' => $env->get('memcached.username', null),
        'password' => $env->get('memcached.password', null),
    ],
];
