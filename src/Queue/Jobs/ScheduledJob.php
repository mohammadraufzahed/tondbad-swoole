<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs;

use TondbadSwoole\Scheduling\ScheduleRegistry;
use TondbadSwoole\Scheduling\TaskRunner;
use TondbadSwoole\Scheduling\Tasks\TaskFactory;
use TondbadSwoole\Support\helpers;

class ScheduledJob extends Job
{
    public function __construct(
        public readonly array $taskConfig,
        public readonly ?string $outputPath = null,
        public readonly ?string $scheduleId = null,
        public readonly ?string $runKey = null,
    ) {
    }

    public function handle(): void
    {
        $container = app()?->container;

        if ($container === null) {
            throw new \RuntimeException('ScheduledJob requires a booted application container.');
        }

        $registry = $container->make(ScheduleRegistry::class);
        $task = TaskFactory::make($this->taskConfig, $registry);
        $runner = new TaskRunner($container, app()->basePath(), $registry);

        $runner->run($task, $this->outputPath);
    }
}
