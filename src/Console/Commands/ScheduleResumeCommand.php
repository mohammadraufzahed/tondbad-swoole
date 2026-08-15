<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Scheduling\Scheduler;

class ScheduleResumeCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:resume';
    }

    public function getDescription(): string
    {
        return 'Resume a paused scheduled task by id.';
    }

    public function run(array $args): int
    {
        $id = $args[0] ?? null;

        if ($id === null) {
            fwrite(STDERR, "Usage: schedule:resume <id>\n");

            return 1;
        }

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $scheduler = $app->container->make(Scheduler::class);
        $scheduler->resume((string) $id);

        fwrite(STDOUT, "Resumed scheduled task: {$id}\n");

        return 0;
    }
}
