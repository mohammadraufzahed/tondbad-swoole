<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Scheduling\Scheduler;

#[AsCommand('schedule:resume', 'Resume a paused scheduled task by id.')]
class ScheduleResumeCommand extends Command
{
    #[Argument('id', mode: InputArgument::REQUIRED, description: 'Scheduled task id')]
    public string $id;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $scheduler = $app->container->make(Scheduler::class);
        $scheduler->resume($this->id);

        $output->success("Resumed scheduled task: {$this->id}");

        return 0;
    }
}
