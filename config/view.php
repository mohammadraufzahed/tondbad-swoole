<?php

declare(strict_types=1);

return [
    'paths' => [
        $basePath . '/resources/views',
    ],
    'compiled' => $basePath . '/storage/cache/views',
    'component_paths' => [
        $basePath . '/app/View/Components',
    ],
    'cache_enabled' => true,
    'live' => [
        'enabled' => (bool) $env->get('view.live.enabled', false),
        'transport' => $env->get('view.live.transport', 'http'),
    ],
];
