<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use TondbadSwoole\Scheduling\Scheduler;

class ScheduleRunCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:run';
    }

    public function getDescription(): string
    {
        return 'Manually run a scheduled task by id.';
    }

    public function run(array $args): int
    {
        $id = $args[0] ?? null;

        if ($id === null) {
            fwrite(STDERR, "Usage: schedule:run <id>\n");

            return 1;
        }

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $scheduler = $app->container->make(Scheduler::class);

        if ($scheduler->trigger((string) $id, new DateTimeImmutable())) {
            fwrite(STDOUT, "Ran scheduled task: {$id}\n");

            return 0;
        }

        fwrite(STDERR, "Scheduled task not found or not due: {$id}\n");

        return 1;
    }
}
