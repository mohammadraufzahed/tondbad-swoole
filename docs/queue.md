# Queue & Jobs

The queue system dispatches background jobs to OpenSwoole TaskWorkers, Redis, or a database table.

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

use TondbadSwoole\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        public readonly string $to,
    ) {
    }

    public function handle(): void
    {
        $mailer = app()->container->make(Mailer::class);
        $mailer->to($this->to)->send(new WelcomeMail());
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

## Sync driver

The `sync` driver executes the job immediately in the same process. It is useful for local development and testing.

## Database queue

The `database` driver stores jobs in a `jobs` table and uses a `QueueWorker` to poll:

```bash
php bin/tondbad queue:work --connection=database --queue=default --tries=3 --sleep=3
```

The worker table is created automatically when the first job is pushed.

## Redis queue

The `redis` driver pushes jobs to a Redis list and pops them in the worker:

```bash
php bin/tondbad queue:work --connection=redis --queue=emails
```

## Failed jobs

Jobs that fail after the configured number of tries are stored in a `failed_jobs` table (or the configured store) and can be inspected or retried later.

## Job lifecycle

1. `dispatch()` serializes the job and pushes it to the configured connection.
2. `queue:work` pops the next job.
3. The container builds the job and calls `handle()`.
4. On success, the job is deleted.
5. On failure, the attempts counter is incremented and the job is released or moved to failed jobs.
