<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs\Backoff;

class BackoffFactory
{
    public static function make(int|array|BackoffStrategy $config): BackoffStrategy
    {
        if ($config instanceof BackoffStrategy) {
            return $config;
        }

        if (is_int($config)) {
            return new FixedBackoff($config);
        }

        $type = $config['type'] ?? 'fixed';
        $delay = (int) ($config['delay'] ?? 0);
        $max = (int) ($config['max'] ?? 0);

        if ($type === 'exponential') {
            return new ExponentialBackoff($delay, $max);
        }

        return new FixedBackoff($delay);
    }
}
