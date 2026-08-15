<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use InvalidArgumentException;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class TaskFactory
{
    public static function make(array $config, ?ScheduleRegistry $registry = null): Task
    {
        $type = $config['type'] ?? null;

        return match ($type) {
            'command' => new CommandTask($config['command'] ?? '', $config['parameters'] ?? []),
            'callable' => new CallableTask($config['callable'] ?? []),
            'closure' => new ClosureTask($config['closureId'] ?? '', $registry?->resolve($config['closureId'] ?? '')),
            'exec' => new ExecTask($config['command'] ?? '', $config['parameters'] ?? []),
            'queue' => new QueueTask(
                $config['jobClass'] ?? '',
                $config['payload'] ?? [],
                $config['queue'] ?? null,
                $config['connection'] ?? null,
                $config['serialized'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unknown scheduled task type: {$type}"),
        };
    }
}
