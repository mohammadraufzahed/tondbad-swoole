<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use RuntimeException;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class CommandTask implements Task
{
    public function __construct(
        private readonly string $command,
        private readonly array $parameters = [],
    ) {
    }

    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed
    {
        $app = $container->make(Application::class);

        return $app->run(array_merge(['tondbad', $this->command], $this->parameters));
    }

    public function toArray(): array
    {
        return [
            'type' => 'command',
            'command' => $this->command,
            'parameters' => $this->parameters,
        ];
    }
}
