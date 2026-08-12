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

    public function find(int $id): ?array
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        return $row !== null ? $row : null;
    }

    public function forQueue(string $queue): array
    {
        return $this->connection->table($this->table)->where('queue', $queue)->get();
    }

    public function delete(int $id): bool
    {
        return $this->connection->table($this->table)->where('id', $id)->delete() > 0;
    }

    public function flush(?string $queue = null): int
    {
        $query = $this->connection->table($this->table);

        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return $query->delete();
    }
}
