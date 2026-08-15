<?php

declare(strict_types=1);

use TondbadSwoole\Core\Env;

return [
    'timezone' => $env->get('schedule.timezone', date_default_timezone_get()),
    'store' => $env->get('schedule.store', 'memory'),
    'locks' => $env->get('schedule.locks', 'file'),
    'node_id' => $env->get('schedule.node_id', null),
    'worker' => [
        'sleep' => (int) $env->get('schedule.worker.sleep', 60),
        'lease' => (int) $env->get('schedule.worker.lease', 3600),
    ],
];
