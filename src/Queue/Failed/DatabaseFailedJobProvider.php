<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Failed;

use Throwable;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Queue\Jobs\Job;

class DatabaseFailedJobProvider implements FailedJobProviderInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'failed_jobs',
    ) {
    }

    public function log(Job $job, Throwable $exception): mixed
    {
        return $this->connection->table($this->table)->insertGetId([
            'connection' => 'database',
            'queue' => $job->getQueue() ?? 'default',
            'payload' => serialize($job),
            'exception' => $exception->getMessage(),
            'failed_at' => time(),
        ]);
    }
}
