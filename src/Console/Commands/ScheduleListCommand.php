<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Scheduling\Schedule;

#[AsCommand('schedule:list', 'List all scheduled tasks.')]
class ScheduleListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $config = $app->container->make(Config::class);
        $schedule = $app->container->make(Schedule::class);
        $timezone = new DateTimeZone((string) $config->get('schedule.timezone', date_default_timezone_get()));
        $now = new DateTimeImmutable('now', $timezone);
        $events = $schedule->events();

        if (count($events) === 0) {
            $output->writeln('No scheduled tasks.');

            return 0;
        }

        $rows = [];

        foreach ($events as $event) {
            $rows[] = [
                $event->getExpression(),
                $event->getDescription(),
                $event->getNextRunDate($now)->format('Y-m-d H:i:s e'),
            ];
        }

        $output->table(['Expression', 'Description', 'Next Run'], $rows);

        return 0;
    }
}
