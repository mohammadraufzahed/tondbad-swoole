<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Triggers;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use TondbadSwoole\Scheduling\Contracts\Trigger;

class TriggerFactory
{
    public static function make(array $config): Trigger
    {
        $type = $config['type'] ?? null;

        return match ($type) {
            'cron' => new CronTrigger($config['expression'] ?? '* * * * *'),
            'interval' => new IntervalTrigger(
                $config['seconds'] ?? 60,
                isset($config['startAt']) ? new DateTimeImmutable($config['startAt']) : null,
            ),
            'once' => new OnceTrigger(new DateTimeImmutable($config['at'] ?? 'now')),
            'delay' => new DelayTrigger(new DateTimeImmutable($config['runAt'] ?? 'now')),
            default => throw new InvalidArgumentException("Unknown trigger type: {$type}"),
        };
    }
}
