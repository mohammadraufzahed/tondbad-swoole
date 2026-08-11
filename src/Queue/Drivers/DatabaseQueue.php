<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueInterface;

class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 60,
    ) {
    }

    public function push(Job $job, ?string $queue = null): mixed
    {
        $payload = $this->createPayload($job);

        return $this->connection->table($this->table)->insertGetId([
            'queue' => $queue ?? $this->defaultQueue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue ??= $this->defaultQueue;

        $job = $this->getNextAvailableJob($queue);

        if ($job === null) {
            return null;
        }

        $this->markJobAsReserved($job['id'], $job['attempts']);

        return $this->createJob($job);
    }

    public function delete(int $id): bool
    {
        return $this->connection->table($this->table)->where('id', $id)->delete() > 0;
    }

    public function release(?int $id, int $delay = 0): bool
    {
        if ($id === null) {
            return false;
        }

        $table = $this->connection->getGrammar()->wrapTable($this->table);

        return $this->connection->update(
            "update {$table} set reserved_at = null, available_at = ? where id = ?",
            [time() + $delay, $id]
        ) > 0;
    }

    public function size(?string $queue = null): int
    {
        return $this->connection->table($this->table)
            ->where('queue', $queue ?? $this->defaultQueue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', time())
            ->count();
    }

    protected function getNextAvailableJob(string $queue): ?array
    {
        $expiration = time() - $this->retryAfter;

        $rows = $this->connection->table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->where(function ($query) use ($expiration): void {
                $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $expiration);
            })
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get();

        return $rows[0] ?? null;
    }

    protected function markJobAsReserved(int $id, int $attempts): void
    {
        $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => time(),
                'attempts' => $attempts + 1,
            ]);
    }

    protected function createPayload(Job $job): string
    {
        return serialize($job);
    }

    protected function createJob(array $job): Job
    {
        /** @var Job $instance */
        $instance = unserialize($job['payload']);
        $instance->setJobId((int) $job['id']);
        $instance->setAttempts((int) $job['attempts']);

        return $instance;
    }
}
