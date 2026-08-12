<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Scheduling\CronExpression;
use TondbadSwoole\Scheduling\Event;
use TondbadSwoole\Scheduling\Schedule;

beforeEach(function () {
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');
    $this->schedule = $this->app->container->make(Schedule::class);
});

it('parses cron macros and expressions', function () {
    $cron = new CronExpression('@daily');

    expect($cron->getExpression())->toBe('0 0 * * *');
    expect($cron->isDue(new DateTimeImmutable('2026-01-01 00:00:00')))->toBeTrue();
    expect($cron->isDue(new DateTimeImmutable('2026-01-01 12:00:00')))->toBeFalse();

    $everyFive = new CronExpression('*/5 * * * *');

    expect($everyFive->isDue(new DateTimeImmutable('2026-01-01 00:05:00')))->toBeTrue();
    expect($everyFive->isDue(new DateTimeImmutable('2026-01-01 00:06:00')))->toBeFalse();
});

it('calculates the next run date for an expression', function () {
    $cron = new CronExpression('0 15 * * *');
    $from = new DateTimeImmutable('2026-01-01 10:00:00');

    expect($cron->getNextRunDate($from)->format('Y-m-d H:i'))->toBe('2026-01-01 15:00');
});

it('creates scheduled events with fluent methods', function () {
    $event = $this->schedule->call(fn () => null)->everyMinute();

    expect($event)->toBeInstanceOf(Event::class);
    expect($event->getExpression())->toBe('* * * * *');
    expect($event->isDue(new DateTimeImmutable('2026-01-01 00:00:00')))->toBeTrue();
});

it('runs due call events and skips non-due events', function () {
    $ran = false;

    $this->schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->everyMinute();

    $count = $this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 00:00:00'));

    expect($count)->toBe(1);
    expect($ran)->toBeTrue();
});

it('dispatches a scheduled job onto the queue', function () {
    $job = new ScheduledTestJob();

    $this->schedule->job($job)->everyMinute();

    $this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 00:00:00'));

    expect(ScheduledTestJob::$ran)->toBeTrue();
});

it('filters events using between and unlessBetween', function () {
    $ran = false;

    $this->schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->everyMinute()
        ->between('09:00', '17:00');

    expect($this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 12:00:00')))->toBe(1);
    expect($ran)->toBeTrue();

    $ran = false;

    expect($this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 08:00:00')))->toBe(0);
    expect($ran)->toBeFalse();

    $ran = false;

    $this->schedule
        ->events()[0]
        ->unlessBetween('09:00', '17:00');

    expect($this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 08:00:00')))->toBe(1);
    expect($ran)->toBeTrue();
});

it('respects event timezones', function () {
    $ran = false;

    $this->schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->hourlyAt(0)
        ->timezone('Asia/Tehran');

    $utcNoon = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));

    expect($this->schedule->runDueEvents($utcNoon))->toBe(0);

    $utc0830 = new DateTimeImmutable('2026-01-01 08:30:00', new DateTimeZone('UTC'));

    expect($this->schedule->runDueEvents($utc0830))->toBe(1);
    expect($ran)->toBeTrue();
});

it('captures event output to a file', function () {
    $outputPath = sys_get_temp_dir() . '/tondbad-schedule-' . uniqid() . '.log';

    $this->schedule
        ->call(function () {
            echo 'scheduled output';
        })
        ->everyMinute()
        ->appendOutputTo($outputPath);

    $this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 00:00:00'));

    expect(file_exists($outputPath))->toBeTrue();
    expect(file_get_contents($outputPath))->toContain('scheduled output');

    @unlink($outputPath);
});

it('prevents overlapping runs with file locks', function () {
    $lockFile = $this->app->basePath('storage/framework/schedule-' . md5('overlapping') . '.lock');

    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }

    if (!is_dir(dirname($lockFile))) {
        @mkdir(dirname($lockFile), 0775, true);
    }

    $ran = false;

    $event = $this->schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->everyMinute()
        ->description('overlapping')
        ->withoutOverlapping();

    $handle = fopen($lockFile, 'c');
    flock($handle, LOCK_EX);

    $ranCount = $this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 00:00:00'));

    expect($ranCount)->toBe(0);
    expect($ran)->toBeFalse();

    flock($handle, LOCK_UN);
    fclose($handle);

    $ranCount = $this->schedule->runDueEvents(new DateTimeImmutable('2026-01-01 00:00:00'));

    expect($ranCount)->toBe(1);
    expect($ran)->toBeTrue();

    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
});

it('lists scheduled tasks with next run dates', function () {
    $this->schedule->call(fn () => null)->daily()->description('daily job');

    $list = [];

    foreach ($this->schedule->events() as $event) {
        $list[] = [
            'expression' => $event->getExpression(),
            'description' => $event->getDescription(),
            'next' => $event->getNextRunDate(new DateTimeImmutable('2026-01-01 00:00:00'))->format('Y-m-d H:i:s'),
        ];
    }

    expect($list)->toHaveCount(1);
    expect($list[0]['expression'])->toBe('0 0 * * *');
    expect($list[0]['description'])->toBe('daily job');
});

class ScheduledTestJob extends Job
{
    public static bool $ran = false;

    public function handle(): void
    {
        self::$ran = true;
    }
}
