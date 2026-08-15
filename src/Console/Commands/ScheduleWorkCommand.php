<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Scheduling\Scheduler;
use TondbadSwoole\Scheduling\SchedulerWorker;

class ScheduleWorkCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:work';
    }

    public function getDescription(): string
    {
        return 'Run scheduled tasks in a loop.';
    }

    public function run(array $args): int
    {
        $options = $this->parseOptions($args);
        $runOnce = isset($options['run-once']);
        $sleep = isset($options['sleep']) ? (int) $options['sleep'] : 60;
        $maxRuns = isset($options['max-runs']) ? (int) $options['max-runs'] : 0;

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $config = $app->container->make(Config::class);
        $nodeId = $options['node-id'] ?? $config->get('schedule.node_id') ?? null;
        $timezone = new DateTimeZone((string) $config->get('schedule.timezone', date_default_timezone_get()));

        $scheduler = $app->container->make(Scheduler::class);
        $dispatcher = $app->container->has(EventDispatcher::class)
            ? $app->container->make(EventDispatcher::class)
            : null;

        $worker = new SchedulerWorker($scheduler, $dispatcher, is_string($nodeId) && $nodeId !== '' ? $nodeId : null);

        $worker->run(new DateTimeImmutable('now', $timezone), $runOnce, $sleep, $maxRuns > 0 ? $maxRuns : null);

        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }

            $option = substr($arg, 2);
            [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
            $options[$key] = $value === true ? true : $value;
        }

        return $options;
    }
}
