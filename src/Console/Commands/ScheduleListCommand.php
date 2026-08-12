<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Scheduling\Schedule;

class ScheduleListCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:list';
    }

    public function getDescription(): string
    {
        return 'List all scheduled tasks.';
    }

    public function run(array $args): int
    {
        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $config = $app->container->make(Config::class);
        $schedule = $app->container->make(Schedule::class);
        $timezone = new DateTimeZone((string) $config->get('schedule.timezone', date_default_timezone_get()));
        $now = new DateTimeImmutable('now', $timezone);
        $events = $schedule->events();

        if (count($events) === 0) {
            fwrite(STDOUT, "No scheduled tasks.\n");

            return 0;
        }

        fwrite(STDOUT, sprintf("%-28s %-30s %-30s\n", 'Expression', 'Description', 'Next Run'));
        fwrite(STDOUT, str_repeat('-', 88) . "\n");

        foreach ($events as $event) {
            $next = $event->getNextRunDate($now)->format('Y-m-d H:i:s e');
            fwrite(STDOUT, sprintf(
                "%-28s %-30s %-30s\n",
                $event->getExpression(),
                $event->getDescription(),
                $next,
            ));
        }

        return 0;
    }
}
