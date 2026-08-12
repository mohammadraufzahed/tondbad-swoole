# Queue & Jobs

The queue system dispatches background jobs to OpenSwoole TaskWorkers, Redis, or a database table. The database driver is the default persistent backend and supports delayed jobs, priorities, retries, backoff, deduplication, metrics, and bulk dispatch.

## Configuration

`config/queue.php`:

```php
<?php

declare(strict_types=1);

return [
    'default' => $env->get('queue.default', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => $env->get('queue.database.connection', null),
            'table' => $env->get('queue.database.table', 'jobs'),
            'queue' => $env->get('queue.database.queue', 'default'),
            'retry_after' => (int) $env->get('queue.database.retry_after', 60),
            'pause_table' => $env->get('queue.database.pause_table', 'queue_pauses'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => $env->get('queue.redis.connection', 'default'),
            'queue' => $env->get('queue.redis.queue', 'default'),
            'retry_after' => (int) $env->get('queue.redis.retry_after', 60),
        ],
    ],

    'failed' => [
        'driver' => $env->get('queue.failed.driver', 'database'),
        'database' => $env->get('queue.failed.database', null),
        'table' => $env->get('queue.failed.table', 'failed_jobs'),
    ],
];
```

## Creating a job

```bash
php bin/tondbad make:job SendWelcomeEmail
```

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use TondbadSwoole\Queue\Jobs\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        public readonly string $to,
    ) {
    }

    public function handle(): void
    {
        // send email
    }
}
```

## Dispatching jobs

```php
(new SendWelcomeEmail('ava@example.com'))->dispatch();

// on a specific queue
(new SendWelcomeEmail('ava@example.com'))->dispatch('emails');

// on a specific connection
(new SendWelcomeEmail('ava@example.com'))->dispatch('default', 'redis');
```

## Job options

```php
use TondbadSwoole\Queue\Jobs\Backoff\ExponentialBackoff;

(new SendWelcomeEmail('ava@example.com'))
    ->delay(60)
    ->priority(10)
    ->tries(3)
    ->backoff(new ExponentialBackoff(10, max: 300))
    ->timeout(30)
    ->jobId('welcome-ava-123')
    ->dispatch('emails');
```

- `delay(int $seconds)` — wait before the job becomes available.
- `priority(int $priority)` — lower numbers are processed first.
- `tries(int $tries)` — maximum attempts before the job is marked as failed.
- `backoff(int|array|BackoffStrategy $config)` — delay between retries; supports `fixed` and `exponential`.
- `timeout(int $seconds)` — maximum execution time hint.
- `jobId(string $id)` — custom deduplication id; duplicate jobs are not inserted while an identical one is waiting, delayed, or active.
- `removeOnComplete(bool $remove)` — keep or remove the job row on success (default: true).
- `removeOnFail(bool $remove)` — keep or remove the job row on failure (default: true).

## Sync driver

The `sync` driver executes the job immediately in the same process. It is useful for local development and testing.

## Database queue

The `database` driver stores jobs in a `jobs` table and uses a `Worker` to poll:

```bash
php bin/tondbad queue:work --connection=database --queue=default --tries=3 --sleep=3
```

The `jobs` table is created by running `php bin/tondbad migrate`.

## Queue API

You can also interact with a queue instance directly:

```php
$queue = queue('database');

// add a single job
$queue->add($job, 'emails', ['delay' => 60]);

// add many jobs
$queue->addBulk([
    $jobOne,
    $jobTwo,
]);

// inspect a job by id
$job = $queue->getJob($id);

// queue metrics
$metrics = $queue->getMetrics('emails');
// ['waiting' => 10, 'active' => 2, 'delayed' => 5, 'failed' => 0, ...]

// remove all jobs from a queue
$queue->drain('emails');

// remove old jobs (and failed jobs) older than the grace period in seconds
$queue->clean(86400, 'emails');

// pause and resume processing for a queue
$queue->pause('emails');
$queue->resume('emails');
```

## Redis queue

The `redis` driver pushes jobs to a Redis list and pops them in the worker:

```bash
php bin/tondbad queue:work --connection=redis --queue=emails
```

## Failed jobs

Jobs that fail after the configured number of tries are stored in a `failed_jobs` table (or the configured store) and can be inspected or retried later.

## Job lifecycle

1. `dispatch()` or `queue()->add()` serializes the job and pushes it to the configured connection.
2. `queue:work` pops the next available job using an atomic reservation so only one worker can process it.
3. The container builds the job and calls `handle()`.
4. On success, the job is deleted.
5. On failure, the attempts counter is checked; if the maximum is reached the job is moved to failed jobs, otherwise it is released with the configured backoff delay.
