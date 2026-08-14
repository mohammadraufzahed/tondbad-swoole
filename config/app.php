<?php

declare(strict_types=1);

return [
    'name' => $env->get('app.name', 'Tondbad Framework'),
    'type' => $env->get('app.type', 'http'),
    'debug' => (bool) $env->get('app.debug', false),
    'key' => $env->get('app.key', 'tondbad-default-key-change-me'),
    'middlewares' => [],
    'commands' => [],
    'route_cache_file' => $env->get('app.route_cache_file', $basePath . '/storage/cache/routes.cache.php'),
    'framework_cache_dir' => $env->get('app.framework_cache_dir', $basePath . '/storage/framework'),
    'paths' => [
        'listeners' => $env->get('app.paths.listeners', 'app/Listeners'),
        'commands' => $env->get('app.paths.commands', 'app/Console/Commands'),
    ],
    'namespaces' => [
        'listeners' => $env->get('app.namespaces.listeners', 'App\\Listeners\\'),
        'commands' => $env->get('app.namespaces.commands', 'App\\Console\\Commands\\'),
    ],
    'logging' => [
        'path' => $env->get('app.logging.path', $basePath . '/storage/logs/app.log'),
        'level' => $env->get('app.logging.level', 'info'),
    ],
    'hook_flags' => (int) $env->get('app.hook_flags', defined('OpenSwoole\Runtime::HOOK_ALL') ? \OpenSwoole\Runtime::HOOK_ALL : 0),
    'http' => [
        'host' => $env->get('app.http.host', '0.0.0.0'),
        'port' => $env->get('app.http.port', 9501),
        'mode' => $env->get('app.http.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
        'sock_type' => $env->get('app.http.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
        'settings' => [
            'enable_coroutine' => true,
        ],
    ],
    'grpc' => [
        'host' => $env->get('app.grpc.host', '0.0.0.0'),
        'port' => $env->get('app.grpc.port', 9502),
        'mode' => $env->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
        'sock_type' => $env->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
        'settings' => [
            'enable_coroutine' => true,
        ],
    ],
];
