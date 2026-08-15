<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Scheduling\Scheduler;

class SchedulePauseCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:pause';
    }

    public function getDescription(): string
    {
        return 'Pause a scheduled task by id.';
    }

    public function run(array $args): int
    {
        $id = $args[0] ?? null;

        if ($id === null) {
            fwrite(STDERR, "Usage: schedule:pause <id>\n");

            return 1;
        }

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $scheduler = $app->container->make(Scheduler::class);
        $scheduler->pause((string) $id);

        fwrite(STDOUT, "Paused scheduled task: {$id}\n");

        return 0;
    }
}
