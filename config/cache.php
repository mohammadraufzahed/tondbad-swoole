<?php

use TondbadSwoole\Core\Env;

return [
    'redis' => [
        'scheme' => Env::get('redis.scheme', 'tcp'), // Connection scheme (tcp, unix, tls)
        'host' => Env::get('redis.host', '127.0.0.1'), // Redis server host
        'port' => Env::get('redis.port', 6379), // Redis server port
        'path' => Env::get('redis.path', null), // Path for unix socket connections
        'password' => Env::get('redis.password', null), // Redis password for authentication
        'database' => Env::get('redis.database', 0), // Redis database index
        'timeout' => Env::get('redis.timeout', 5.0), // Connection timeout in seconds
        'read_write_timeout' => Env::get('redis.read_write_timeout', null), // Read/write operation timeout
        'persistent' => Env::get('redis.persistent', false), // Persistent connection (true/false)
        'retry_interval' => Env::get('redis.retry_interval', 0), // Retry interval for reconnection attempts (milliseconds)

        // SSL options (if using TLS)
        'ssl' => [
            'enabled' => Env::get('redis.ssl.enabled', false), // Enable SSL
            'cafile' => Env::get('redis.ssl.cafile', null), // Path to the CA file
            'verify_peer' => Env::get('redis.ssl.verify_peer', true), // Verify peer SSL certificate
            'verify_peer_name' => Env::get('redis.ssl.verify_peer_name', true), // Verify peer name during SSL handshake
        ],

        // Redis Cluster support
        'cluster' => [
            'enabled' => Env::get('redis.cluster.enabled', false), // Enable Redis clustering
            'nodes' => [
                Env::get('redis.cluster.node_1', '127.0.0.1:6379'), // Cluster node 1
                Env::get('redis.cluster.node_2', null), // Cluster node 2
                Env::get('redis.cluster.node_3', null), // Cluster node 3
            ],
        ],

        // Redis Sentinel support
        'sentinel' => [
            'enabled' => Env::get('redis.sentinel.enabled', false), // Enable Redis Sentinel
            'service' => Env::get('redis.sentinel.service', 'mymaster'), // Sentinel service name
            'nodes' => [
                Env::get('redis.sentinel.node_1', '127.0.0.1:26379'), // Sentinel node 1
                Env::get('redis.sentinel.node_2', null), // Sentinel node 2
                Env::get('redis.sentinel.node_3', null), // Sentinel node 3
            ],
        ],

        // Additional Redis client options
        'options' => [
            'prefix' => Env::get('redis.options.prefix', ''), // Key prefix for Redis keys
            'serializer' => Env::get('redis.options.serializer', 'php'), // Serializer for Redis (php, json, igbinary)
            'compression' => Env::get('redis.options.compression', null), // Compression (lzf, zstd)
        ],
    ],
    'memcached' => [
        'host' => Env::get('memcached.host', '127.0.0.1'),
        'port' => (int)Env::get('memcached.port', 11211),
        'username' => Env::get('memcached.username', null),
        'password' => Env::get('memcached.password', null)
    ]
];
