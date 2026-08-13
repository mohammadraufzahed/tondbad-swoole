# Task Scheduling

Tondbād's scheduler runs a single `ScheduleWorker` process that evaluates cron expressions and dispatches scheduled events.

## Configuration

`routes/console.php` is the conventional place to define schedules. It returns a callable that receives the `Schedule` instance:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Console\Schedule;

return function (Schedule $schedule): void {
    $schedule->command('report:daily')->daily();
    $schedule->call(fn () => cleanupOldLogs())->everyTenMinutes();
    $schedule->job(new CleanupJob())->hourly();
};
```

## Schedule fluent API

```php
$schedule->command('report:daily')->daily();
$schedule->command('report:weekly')->weekly();
$schedule->command('report:hourly')->hourly();
$schedule->command('report:every-minute')->everyMinute();
$schedule->command('report:custom')->cron('*/5 * * * *');
```

Modifiers:

```php
$schedule->command('report:daily')
    ->daily()
    ->withoutOverlapping()
    ->timezone('UTC')
    ->between('08:00', '20:00');
```

## Schedule types

- `command(string $name)` — runs a `tondbad` command.
- `call(callable $callback)` — runs any callable.
- `job(Job $job)` — dispatches a queue job.
- `exec(string $command, array $args)` — runs a system command.

## Running the scheduler

```bash
php bin/tondbad schedule:work
```

The scheduler process keeps running, checking due events every minute. When OpenSwoole is available the worker enables `Runtime::HOOK_ALL`, wraps the loop in a coroutine, and uses `OpenSwoole\Coroutine\System::sleep()` instead of blocking `sleep()`, so scheduled callbacks that hit Redis, the database, or `curl_*` calls yield instead of blocking the event loop.

Use a single process to avoid duplicate runs; `withoutOverlapping()` can be backed by Redis locks if configured. To run due events once and exit:

```bash
php bin/tondbad schedule:work --run-once
```

## Listing scheduled tasks

```bash
php bin/tondbad schedule:list
```

This prints each event, its cron expression, and the next run date.

## Preventing overlapping

```php
$schedule->call(fn () => generateReport())
    ->hourly()
    ->withoutOverlapping();
```

By default `withoutOverlapping` uses a file lock. For multi-server deployments, configure a Redis-backed lock store.

## Output

Scheduled command output can be captured to a log file:

```php
$schedule->command('report:daily')
    ->daily()
    ->appendOutputTo(app()->basePath('storage/logs/schedule/report.log'));
```

> Use `appendOutputTo` to append command output to the configured path when the command is executed. `emailOutputTo` is accepted for API compatibility but currently does not send email.
