<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Scheduling\Schedule;

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
        $schedule = $app->container->make(Schedule::class);
        $timezone = new DateTimeZone((string) $config->get('schedule.timezone', date_default_timezone_get()));

        $totalRuns = 0;

        do {
            $now = new DateTimeImmutable('now', $timezone);
            $totalRuns += $schedule->runDueEvents($now);

            if ($runOnce) {
                break;
            }

            if ($maxRuns > 0 && $totalRuns >= $maxRuns) {
                break;
            }

            sleep($sleep);
        } while (true);

        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                $options[$key] = $value === true ? true : $value;
            }
        }

        return $options;
    }
}
