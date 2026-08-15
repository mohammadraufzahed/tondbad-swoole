<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Scheduling\Scheduler;
use TondbadSwoole\Scheduling\SchedulerWorker;

#[AsCommand('schedule:work', 'Run scheduled tasks in a loop.', coroutine: false)]
class ScheduleWorkCommand extends Command
{
    #[Option('run-once', mode: InputOption::VALUE_NONE, description: 'Run due tasks once and exit')]
    public bool $runOnce = false;

    #[Option('sleep', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Seconds to sleep between polls', default: 60)]
    public int $sleep = 60;

    #[Option('max-runs', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Maximum number of poll cycles', default: 0)]
    public int $maxRuns = 0;

    #[Option('node-id', mode: InputOption::VALUE_OPTIONAL, description: 'Worker node id')]
    public ?string $nodeId = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $config = $app->container->make(Config::class);
        $nodeId = $this->nodeId ?? $config->get('schedule.node_id') ?? null;
        $timezone = new DateTimeZone((string) $config->get('schedule.timezone', date_default_timezone_get()));

        $scheduler = $app->container->make(Scheduler::class);
        $dispatcher = $app->container->has(EventDispatcher::class)
            ? $app->container->make(EventDispatcher::class)
            : null;

        $worker = new SchedulerWorker($scheduler, $dispatcher, is_string($nodeId) && $nodeId !== '' ? $nodeId : null);

        $worker->run(new DateTimeImmutable('now', $timezone), $this->runOnce, $this->sleep, $this->maxRuns > 0 ? $this->maxRuns : null);

        return 0;
    }
}
