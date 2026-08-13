# Queue & Jobs

The queue system dispatches background jobs to OpenSwoole TaskWorkers, Redis, or a database table. The `database` and `redis` drivers are persistent backends that support delayed jobs, priorities, retries, backoff, deduplication, metrics, flow jobs, and bulk dispatch.

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
            'scheme' => $env->get('queue.redis.scheme', 'tcp'),
            'host' => $env->get('queue.redis.host', '127.0.0.1'),
            'port' => (int) $env->get('queue.redis.port', 6379),
            'password' => $env->get('queue.redis.password', null),
            'database' => (int) $env->get('queue.redis.database', 0),
            'prefix' => $env->get('queue.redis.prefix', 'tondbad'),
            'queue' => $env->get('queue.redis.queue', 'default'),
            'retry_after' => (int) $env->get('queue.redis.retry_after', 60),
            'block_for' => (int) $env->get('queue.redis.block_for', 1),
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
- `progress(int $value)` — update the job's progress (0-100); persisted to the `progress` column and emits a `progress` event when a queue connection is available.
- `setResult(mixed $value)` — store a return value for the job; persisted to the `result` column when a queue connection is available.
- `getChildrenValues()` — for flow parents, returns an associative array of child results keyed by child job id: `[$id => ['job' => Job, 'result' => mixed, 'status' => string]]`.

## Sync driver

The `sync` driver executes the job immediately in the same process. It is useful for local development and testing.

## Database queue

The `database` driver stores jobs in a `jobs` table and uses a `Worker` to poll:

```bash
php bin/tondbad queue:work --connection=database --queue=default --tries=3 --sleep=3
```

The `jobs` table is created by running `php bin/tondbad migrate`.

When the database backend supports it (PostgreSQL, MySQL 8+), `pop()` reserves the next job atomically with `SELECT ... FOR UPDATE SKIP LOCKED` / `UPDATE ... RETURNING`, so multiple concurrent workers never receive the same job.

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

## Queue events

The `Queue` implementation emits lifecycle events that can be observed with `$queue->on()`:

```php
$queue->on('added', function (array $data) {
    // $data['job'], $data['id'], $data['queue']
});

$queue->on('active', function (array $data) {
    // $data['job'], $data['queue']
});

$queue->on('stalled', function (array $data) {
    // a previously active job with an expired reservation was recovered
});

$queue->on('completed', function (array $data) {
    // $data['job'], $data['result']
});

$queue->on('failed', function (array $data) {
    // $data['job'], $data['exception']
});

$queue->on('progress', function (array $data) {
    // $data['job'], $data['progress']
});

$queue->on('paused', function (array $data) {
    // $data['queue']
});

$queue->on('resumed', function (array $data) {
    // $data['queue']
});

$queue->on('drained', function (array $data) {
    // $data['queue'], $data['count']
});

$queue->on('cleaned', function (array $data) {
    // $data['queue'], $data['count']
});
```

## CLI commands

```bash
# Start a worker
php bin/tondbad queue:work --connection=database --queue=default --tries=3 --sleep=3

# Start with coroutine concurrency (OpenSwoole only) and rate limiting
php bin/tondbad queue:work --connection=database --queue=default --concurrency=4 --rate-limit=10:60

# Show queue metrics
php bin/tondbad queue:status --connection=database --queue=default

# Retry one failed job by its failed_jobs id
php bin/tondbad queue:retry <id> --connection=database --queue=default

# Retry all failed jobs for a queue
php bin/tondbad queue:retry-failed --connection=database --queue=default
```

## Flow jobs

`FlowProducer` builds a parent job that waits until all child jobs finish, then runs and receives the child results:

```php
use TondbadSwoole\Queue\Flow\Flow;
use TondbadSwoole\Queue\Flow\FlowChild;
use TondbadSwoole\Queue\FlowProducer;

$flow = Flow::create(
    new GenerateReport(),
    [
        FlowChild::create(new FetchUsers()),
        FlowChild::create(new FetchOrders()),
    ]
);

$producer = app()->container->make(FlowProducer::class);
$producer->add($flow, 'database', 'default');
```

Children can call `setResult()` inside `handle()`:

```php
class FetchUsers extends Job
{
    public function handle(): void
    {
        $users = // ...
        $this->setResult($users);
    }
}
```

The parent reads the values in its `handle()`:

```php
public function handle(): void
{
    $children = $this->getChildrenValues();

    foreach ($children as $id => ['job' => $job, 'result' => $result]) {
        // $job is the child Job instance, $result is the value from setResult()
    }
}
```

If any child fails after exhausting its `tries`, the parent is marked `failed` automatically.

## Redis queue

The `redis` driver uses `Predis` with Redis lists and sorted sets. Jobs are atomically claimed with `RPOPLPUSH` so only one worker receives each job. Delayed jobs, stale active-job recovery, metrics, flow jobs, and rate limiting are all supported with the same API as the database driver.

```bash
php bin/tondbad queue:work --connection=redis --queue=emails
```

With OpenSwoole you can run multiple coroutine workers safely:

```bash
php bin/tondbad queue:work --connection=redis --queue=emails --concurrency=4
```

`--concurrency` enables `OpenSwoole\Runtime::HOOK_TCP` automatically and processes jobs inside `OpenSwoole\Coroutine::run`. Each coroutine calls the same atomic `pop()` path, so a job is never handled by more than one worker.

`block_for` controls how long `pop()` blocks waiting for a job when the queue is empty. Set it to `0` to poll immediately.

## Rate limiting

Rate limiting is applied atomically when the job is popped. Enable it by setting `queue.rateLimiter.driver` to `database` or by passing `--rate-limit` to `queue:work`.

```bash
# max 10 jobs per 60 seconds for the queue
php bin/tondbad queue:work --connection=database --queue=default --rate-limit=10:60
```

The limit key can be `'queue'` (default), `'class'` (job class name), or any custom string. `RateLimiterInterface::attempt()` increments the counter and returns whether the attempt is allowed in a single step, so concurrent workers cannot overshoot the limit. When the limit is exceeded, the job is released with a delay equal to the remaining window and a `rate_limited` event is emitted.

## Failed jobs

Jobs that fail after the configured number of tries are stored in a `failed_jobs` table (or the configured store) and can be inspected or retried later.

## Job lifecycle

1. `dispatch()` or `queue()->add()` serializes the job and pushes it to the configured connection. An `added` (or `delayed`) event is emitted.
2. `queue:work` pops the next available job using an atomic reservation so only one worker can process it. An `active` event is emitted; if the job was recovered from a stale reservation, a `stalled` event is emitted first.
3. If rate limiting is configured and the limit has been reached, the job is released with a delay and a `rate_limited` event is emitted.
4. The container builds the job and calls `handle()`.
5. `handle()` may call `$this->progress($value)` to update the `progress` column and emit a `progress` event.
6. On success, the job is deleted by default. If `removeOnComplete(false)` was used, the row is kept with `status = completed` and a `completed` event is emitted.
7. On failure, the attempts counter is checked; if the maximum is reached the job is moved to failed jobs (or kept with `status = failed` when `removeOnFail(false)`) and a `failed` event is emitted, otherwise it is released with the configured backoff delay.
8. For flow jobs, the parent waits in `waiting_children` until all children complete. Children pass results back to the parent via `setResult()`. If any child fails, the parent is marked `failed` too.
