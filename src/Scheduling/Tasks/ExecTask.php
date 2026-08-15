<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use RuntimeException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class ExecTask implements Task
{
    public function __construct(
        private readonly string $command,
        private readonly array $parameters = [],
    ) {
    }

    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed
    {
        $line = $this->command;

        foreach ($this->parameters as $parameter) {
            $line .= ' ' . escapeshellarg((string) $parameter);
        }

        passthru($line, $code);

        if ($code !== 0) {
            throw new RuntimeException("Scheduled exec returned exit code {$code}: {$line}");
        }

        return $code;
    }

    public function toArray(): array
    {
        return [
            'type' => 'exec',
            'command' => $this->command,
            'parameters' => $this->parameters,
        ];
    }
}
