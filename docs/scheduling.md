# Task Scheduling

Tondbād's scheduler evaluates cron, interval, one-shot, and delay triggers and dispatches the matching work. In-process tasks run directly, while long or background work is handed to the queue worker through the `ScheduledJob` job.

## Configuration

`routes/console.php` is the conventional place to define schedules. It returns a callable that receives the `Schedule` instance:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Scheduling\Schedule;

return function (Schedule $schedule): void {
    $schedule->command('report:daily')->daily();
    $schedule->call(fn () => cleanupOldLogs())->everyTenMinutes();
    $schedule->job(new CleanupJob())->hourly();
    $schedule->exec('php', ['artisan', 'cache:clear'])->weekly();
};
```

Environment variables:

- `SCHEDULE_STORE` — `memory`, `database`, or `redis`
- `SCHEDULE_LOCKS` — `file` (default), `null`, or a Redis-backed lock provider
- `SCHEDULE_TIMEZONE` — default timezone for expression evaluation

## Schedule fluent API

```php
$schedule->command('report:daily')->daily();
$schedule->command('report:weekly')->weekly();
$schedule->command('report:hourly')->hourly();
$schedule->call(fn () => ping())->everyMinute();
$schedule->call(fn () => ping())->everyFiveMinutes();
$schedule->call(fn () => ping())->cron('*/5 * * * *');

// non-cron triggers
$schedule->call(fn () => heartbeat())->everyFiveSeconds();
$schedule->call(fn () => once())->once();
$schedule->call(fn () => delayed())->delay(30); // seconds
```

Modifiers:

```php
$schedule->command('report:daily')
    ->daily()
    ->withoutOverlapping()
    ->timezone('UTC')
    ->between('08:00', '20:00')
    ->unlessBetween('02:00', '04:00')
    ->appendOutputTo(app()->basePath('storage/logs/report.log'));
```

Rate limits are honored through the same `RateLimiterInterface` used by the queue:

```php
$schedule->call(fn () => pollApi())
    ->everyMinute()
    ->throttle(10, 60);
```

## Schedule types

- `command(string $name)` — runs a `tondbad` command.
- `call(callable $callback)` — runs any callable/closure.
- `job(Job $job)` — pushes the job onto the configured queue.
- `exec(string $command, array $args)` — runs a system command.

## Running the scheduler

```bash
php bin/tondbad schedule:work
```

`schedule:work` starts a single worker that polls for due schedules. The worker is safe under OpenSwoole: it enables `Runtime::HOOK_ALL`, wraps the loop in a coroutine, and sleeps with `OpenSwoole\Coroutine\System::sleep()`.

For a single pass:

```bash
php bin/tondbad schedule:work --run-once
```

### Clustering

Multiple `schedule:work` processes can share a Redis or database store. Each worker generates a unique `node_id` and claims a schedule run with a lease. A stale lock (worker died) is recovered automatically when the lease expires.

To run with a fixed node id:

```bash
php bin/tondbad schedule:work --node-id=worker-1
```

## CLI commands

```bash
php bin/tondbad schedule:list
php bin/tondbad schedule:pause <id>
php bin/tondbad schedule:resume <id>
php bin/tondbad schedule:delete <id>
php bin/tondbad schedule:run <id>
```

Use `schedule:list` to see each schedule id, expression, status, and next run date.

## Preventing overlapping

```php
$schedule->call(fn () => generateReport())
    ->hourly()
    ->withoutOverlapping();
```

By default `withoutOverlapping` uses a file lock. Set `SCHEDULE_LOCKS=redis` to use a distributed Redis lock.

## Output

Scheduled command output can be captured to a log file:

```php
$schedule->command('report:daily')
    ->daily()
    ->appendOutputTo(app()->basePath('storage/logs/schedule/report.log'));
```

## Programmatic control

```php
use TondbadSwoole\Scheduling\Schedule;

$schedule = app()->container->make(Schedule::class);

$schedule->runDueEvents(new DateTimeImmutable());

$scheduler = scheduler();
$scheduler->pause('my-schedule');
$scheduler->resume('my-schedule');
$scheduler->trigger('my-schedule');
```

## Misfire policy

For schedules that were missed (worker was down), the `MisfirePolicy` controls what happens on the next tick:

- `FIRE_ONCE` — fire once for the most recent missed occurrence.
- `FIRE_AND_PROCEED` — fire for every missed occurrence.
- `IGNORE` — skip missed occurrences.
- `SMART` (default) — fire once if only a few are missed, then catch up.

## Events

The scheduler emits `ScheduleEvent` events:

- `schedule.tick`
- `schedule.created`
- `schedule.starting`
- `schedule.ran`
- `schedule.failed`
- `schedule.skipped`

Listen like any other event:

```php
$dispatcher->listen('schedule.failed', function (ScheduleEvent $event) {
    logger()->error('Schedule failed: ' . $event->task, $event->metadata);
});
```
