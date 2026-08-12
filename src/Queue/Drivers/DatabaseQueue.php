<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Jobs\JobStatus;
use TondbadSwoole\Queue\Queue;

class DatabaseQueue extends Queue
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 60,
        private readonly ?string $failedTable = null,
        private readonly string $pauseTable = 'queue_pauses',
    ) {
    }

    public function push(Job $job, ?string $queue = null): mixed
    {
        $queueName = $queue ?? $job->getQueue() ?? $this->defaultQueue;
        $job->onQueue($queueName);

        $customId = $job->getCustomJobId();

        if ($customId !== null) {
            $existing = $this->getExistingIdByCustomId($customId, $queueName);

            if ($existing !== null) {
                return $existing;
            }
        }

        $delay = $job->getDelay();
        $status = $delay > 0 ? JobStatus::Delayed : JobStatus::Waiting;
        $backoff = $job->getBackoff();

        return (int) $this->connection->table($this->table)->insertGetId([
            'queue' => $queueName,
            'payload' => $this->createPayload($job),
            'attempts' => $job->getAttempts(),
            'reserved_at' => null,
            'available_at' => time() + $delay,
            'created_at' => time(),
            'priority' => $job->getPriority() ?? 0,
            'delay' => $delay > 0 ? $delay : null,
            'backoff_type' => $backoff?->getTypeForStorage(),
            'backoff_value' => $backoff?->getValueForStorage(),
            'timeout' => $job->getTimeout(),
            'deduplication_id' => $customId,
            'status' => $status->value,
        ]);
    }

    public function pop(?string $queue = null): ?Job
    {
        $queueName = $queue ?? $this->defaultQueue;

        if ($this->isPaused($queueName)) {
            return null;
        }

        $expiration = time() - $this->retryAfter;
        $table = $this->connection->getGrammar()->wrapTable($this->table);

        while (true) {
            $job = $this->getNextAvailableJob($queueName, $expiration);

            if ($job === null) {
                return null;
            }

            $reserved = $this->connection->update(
                "update {$table} set reserved_at = ?, status = ?, attempts = attempts + 1 where id = ? and (reserved_at is null or reserved_at <= ?)",
                [time(), JobStatus::Active->value, $job['id'], $expiration]
            );

            if ($reserved > 0) {
                $instance = $this->createJob($job);
                $instance->setAttempts((int) $job['attempts'] + 1);

                return $instance;
            }
        }
    }

    public function size(?string $queue = null): int
    {
        $queueName = $queue ?? $this->defaultQueue;
        $expiration = time() - $this->retryAfter;

        return $this->connection->table($this->table)
            ->where('queue', $queueName)
            ->whereIn('status', [JobStatus::Waiting->value, JobStatus::Delayed->value])
            ->where(function ($query) use ($expiration): void {
                $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $expiration);
            })
            ->count();
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

        $status = $delay > 0 ? JobStatus::Delayed->value : JobStatus::Waiting->value;

        return $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
                'status' => $status,
            ]) > 0;
    }

    public function getJob(int $id): ?Job
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        return $this->createJob($row);
    }

    public function getMetrics(?string $queue = null): array
    {
        $queueName = $queue ?? $this->defaultQueue;
        $table = $this->connection->getGrammar()->wrapTable($this->table);

        $metrics = [];

        foreach (JobStatus::cases() as $status) {
            $metrics[$status->value] = 0;
        }

        $rows = $this->connection->select(
            "SELECT status, COUNT(*) as total FROM {$table} WHERE queue = ? GROUP BY status",
            [$queueName]
        );

        foreach ($rows as $row) {
            $metrics[$row['status']] = (int) $row['total'];
        }

        if ($this->failedTable !== null) {
            $metrics['failed'] += $this->connection->table($this->failedTable)
                ->where('queue', $queueName)
                ->count();
        }

        $metrics['failed'] += $this->connection->table($this->table)
            ->where('queue', $queueName)
            ->where('status', JobStatus::Failed->value)
            ->count();

        return $metrics;
    }

    public function drain(?string $queue = null): int
    {
        $queueName = $queue ?? $this->defaultQueue;

        return $this->connection->table($this->table)->where('queue', $queueName)->delete();
    }

    public function clean(int $gracePeriod = 86400, ?string $queue = null): int
    {
        $queueName = $queue ?? $this->defaultQueue;
        $cutoff = time() - $gracePeriod;
        $count = $this->connection->table($this->table)
            ->where('queue', $queueName)
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($this->failedTable !== null) {
            $count += $this->connection->table($this->failedTable)
                ->where('queue', $queueName)
                ->where('failed_at', '<', $cutoff)
                ->delete();
        }

        return $count;
    }

    public function pause(?string $queue = null): void
    {
        $queueName = $queue ?? $this->defaultQueue;
        $now = time();

        $existing = $this->connection->table($this->pauseTable)
            ->where('queue', $queueName)
            ->first();

        if ($existing !== null) {
            $this->connection->table($this->pauseTable)
                ->where('queue', $queueName)
                ->update(['paused' => 1, 'updated_at' => $now]);

            return;
        }

        $this->connection->table($this->pauseTable)->insert([
            'queue' => $queueName,
            'paused' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function resume(?string $queue = null): void
    {
        $queueName = $queue ?? $this->defaultQueue;

        $this->connection->table($this->pauseTable)
            ->where('queue', $queueName)
            ->update(['paused' => 0, 'updated_at' => time()]);
    }

    public function isPaused(string $queue): bool
    {
        $row = $this->connection->table($this->pauseTable)
            ->where('queue', $queue)
            ->where('paused', 1)
            ->first();

        return $row !== null;
    }

    public function markCompleted(int $id): bool
    {
        return $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Completed->value,
            ]) > 0;
    }

    public function markFailed(int $id): bool
    {
        return $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Failed->value,
            ]) > 0;
    }

    protected function getNextAvailableJob(string $queue, int $expiration): ?array
    {
        $rows = $this->connection->table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereIn('status', [JobStatus::Waiting->value, JobStatus::Delayed->value, JobStatus::Active->value])
            ->where(function ($query) use ($expiration): void {
                $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $expiration);
            })
            ->orderBy('priority', 'asc')
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get();

        return $rows[0] ?? null;
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
        $instance->onQueue($job['queue']);

        return $instance;
    }

    protected function getExistingIdByCustomId(string $customId, string $queue): ?int
    {
        $row = $this->connection->table($this->table)
            ->where('queue', $queue)
            ->where('deduplication_id', $customId)
            ->whereIn('status', [JobStatus::Waiting->value, JobStatus::Delayed->value, JobStatus::Active->value])
            ->first();

        return $row !== null ? (int) $row['id'] : null;
    }
}
