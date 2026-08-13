<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use Throwable;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Jobs\JobStatus;
use TondbadSwoole\Queue\Queue;
use TondbadSwoole\Queue\QueueEvents;

class DatabaseQueue extends Queue
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 60,
        private readonly ?string $failedTable = null,
        private readonly string $pauseTable = 'queue_pauses',
        ?QueueEvents $events = null,
    ) {
        parent::__construct($events ?? new QueueEvents());
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
        $childrenCount = $job->getChildrenCount() ?? 0;
        $status = match (true) {
            $childrenCount > 0 => JobStatus::WaitingChildren,
            $delay > 0 => JobStatus::Delayed,
            default => JobStatus::Waiting,
        };
        $availableAt = $childrenCount > 0 ? PHP_INT_MAX : time() + $delay;
        $backoff = $job->getBackoff();

        $id = (int) $this->connection->table($this->table)->insertGetId([
            'queue' => $queueName,
            'payload' => $this->createPayload($job),
            'attempts' => $job->getAttempts(),
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => time(),
            'priority' => $job->getPriority() ?? 0,
            'delay' => $delay > 0 ? $delay : null,
            'backoff_type' => $backoff?->getTypeForStorage(),
            'backoff_value' => $backoff?->getValueForStorage(),
            'timeout' => $job->getTimeout(),
            'deduplication_id' => $customId,
            'parent_id' => $job->getParentId(),
            'children_count' => $job->getChildrenCount() ?? 0,
            'completed_children_count' => 0,
            'status' => $status->value,
        ]);

        $this->emit($status === JobStatus::Delayed ? 'delayed' : 'added', ['job' => $job, 'queue' => $queueName, 'id' => $id]);

        return $id;
    }

    public function pop(?string $queue = null): ?Job
    {
        $queueName = $queue ?? $this->defaultQueue;

        if ($this->isPaused($queueName)) {
            return null;
        }

        if ($this->supportsAtomicPop()) {
            return $this->popAtomically($queueName);
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

                if ($job['status'] === JobStatus::Active->value) {
                    $this->emit('stalled', ['job' => $instance, 'queue' => $queueName]);
                }

                $this->emit('active', ['job' => $instance, 'queue' => $queueName]);

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
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        if ($row !== null && (int) $row['children_count'] > 0) {
            $this->connection->table($this->table)->where('parent_id', $id)->delete();
        }

        return $this->connection->table($this->table)->where('id', $id)->delete() > 0;
    }

    public function release(?int $id, int $delay = 0): bool
    {
        if ($id === null) {
            return false;
        }

        $status = $delay > 0 ? JobStatus::Delayed : JobStatus::Waiting;

        $updated = $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
                'status' => $status->value,
            ]) > 0;

        if ($updated) {
            $this->emit($status === JobStatus::Delayed ? 'delayed' : 'waiting', ['id' => $id]);
        }

        return $updated;
    }

    public function markCompleted(int $id): bool
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        if ($row === null) {
            return false;
        }

        $updated = $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Completed->value,
            ]) > 0;

        if (!$updated) {
            return false;
        }

        $instance = $this->createJob($row);
        $this->emit('completed', ['job' => $instance, 'result' => $instance->getResult()]);

        $parentId = $row['parent_id'] ?? null;

        if ($parentId !== null) {
            $this->completeChild((int) $parentId);
        }

        return true;
    }

    public function markFailed(int $id, ?Throwable $exception = null): bool
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();

        if ($row === null) {
            return false;
        }

        $updated = $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Failed->value,
            ]) > 0;

        if (!$updated) {
            return false;
        }

        $instance = $this->createJob($row);
        $this->emit('failed', ['job' => $instance, 'exception' => $exception]);

        $parentId = $row['parent_id'] ?? null;

        if ($parentId !== null) {
            $this->failParent((int) $parentId, $exception);
        }

        return true;
    }

    public function progress(int $id, int $progress): bool
    {
        return $this->connection->table($this->table)
            ->where('id', $id)
            ->update(['progress' => max(0, min(100, $progress))]) > 0;
    }

    public function setResult(int $id, mixed $value): bool
    {
        return $this->connection->table($this->table)
            ->where('id', $id)
            ->update(['result' => serialize($value)]) > 0;
    }

    public function getChildren(int $parentId): array
    {
        $rows = $this->connection->table($this->table)
            ->where('parent_id', $parentId)
            ->get();

        $children = [];

        foreach ($rows as $row) {
            $job = $this->createJob($row);

            $children[(int) $row['id']] = [
                'job' => $job,
                'result' => $row['result'] !== null ? unserialize($row['result']) : null,
                'status' => $row['status'],
            ];
        }

        return $children;
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
        $count = $this->connection->table($this->table)->where('queue', $queueName)->delete();

        if ($count > 0) {
            $this->emit('drained', ['queue' => $queueName, 'count' => $count]);
        }

        return $count;
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

        if ($count > 0) {
            $this->emit('cleaned', ['queue' => $queueName, 'count' => $count]);
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
        } else {
            $this->connection->table($this->pauseTable)->insert([
                'queue' => $queueName,
                'paused' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->emit('paused', ['queue' => $queueName]);
    }

    public function resume(?string $queue = null): void
    {
        $queueName = $queue ?? $this->defaultQueue;

        $this->connection->table($this->pauseTable)
            ->where('queue', $queueName)
            ->update(['paused' => 0, 'updated_at' => time()]);

        $this->emit('resumed', ['queue' => $queueName]);
    }

    public function isPaused(string $queue): bool
    {
        $row = $this->connection->table($this->pauseTable)
            ->where('queue', $queue)
            ->where('paused', 1)
            ->first();

        return $row !== null;
    }

    protected function completeChild(int $parentId): void
    {
        $table = $this->connection->getGrammar()->wrapTable($this->table);

        $this->connection->update(
            "update {$table} set completed_children_count = completed_children_count + 1 where id = ?",
            [$parentId]
        );

        $parent = $this->connection->table($this->table)->where('id', $parentId)->first();

        if ($parent === null) {
            return;
        }

        $completed = (int) $parent['completed_children_count'];
        $total = (int) $parent['children_count'];

        if ($completed >= $total && $total > 0) {
            $this->connection->table($this->table)
                ->where('id', $parentId)
                ->update([
                    'reserved_at' => null,
                    'available_at' => time(),
                    'status' => JobStatus::Waiting->value,
                ]);

            $this->emit('waiting', ['id' => $parentId, 'queue' => $parent['queue']]);
        }
    }

    protected function failParent(int $parentId, ?Throwable $exception): void
    {
        $parent = $this->connection->table($this->table)->where('id', $parentId)->first();

        if ($parent === null) {
            return;
        }

        $this->connection->table($this->table)
            ->where('id', $parentId)
            ->update([
                'reserved_at' => null,
                'status' => JobStatus::Failed->value,
            ]);

        $instance = $this->createJob($parent);
        $this->emit('failed', ['job' => $instance, 'exception' => $exception]);
    }

    private function supportsAtomicPop(): bool
    {
        $features = $this->connection->getGrammar()->getFeatures();

        return $features->supportsReturning() && $features->supportsForUpdateSkipLocked();
    }

    private function popAtomically(string $queue): ?Job
    {
        $expiration = time() - $this->retryAfter;
        $table = $this->connection->getGrammar()->wrapTable($this->table);
        $now = time();

        $rows = $this->connection->select(
            "WITH next_job AS (
                SELECT id FROM {$table}
                WHERE queue = ? AND available_at <= ? AND status IN (?, ?, ?)
                  AND (reserved_at IS NULL OR reserved_at <= ?)
                ORDER BY priority ASC, available_at ASC, id ASC
                FOR UPDATE SKIP LOCKED
                LIMIT 1
            )
            UPDATE {$table}
            SET reserved_at = ?, status = ?, attempts = attempts + 1
            FROM next_job
            WHERE {$table}.id = next_job.id
            RETURNING {$table}.*",
            [
                $queue,
                $now,
                JobStatus::Waiting->value,
                JobStatus::Delayed->value,
                JobStatus::Active->value,
                $expiration,
                $now,
                JobStatus::Active->value,
            ]
        );

        $job = $rows[0] ?? null;

        if ($job === null) {
            return null;
        }

        $instance = $this->createJob($job);
        $instance->setAttempts((int) $job['attempts'] + 1);

        if ($job['status'] === JobStatus::Active->value) {
            $this->emit('stalled', ['job' => $instance, 'queue' => $queue]);
        }

        $this->emit('active', ['job' => $instance, 'queue' => $queue]);

        return $instance;
    }

    protected function getNextAvailableJob(string $queue, int $expiration): ?array
    {
        $query = $this->connection->table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereIn('status', [JobStatus::Waiting->value, JobStatus::Delayed->value, JobStatus::Active->value])
            ->where(function ($query) use ($expiration): void {
                $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $expiration);
            })
            ->orderBy('priority', 'asc')
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1);

        $features = $this->connection->getGrammar()->getFeatures();

        if ($features->supportsForUpdateSkipLocked()) {
            $query->lockForUpdate()->skipLocked();
        }

        $rows = $query->get();

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

        if ($job['parent_id'] !== null) {
            $instance->setParentId((int) $job['parent_id']);
        }

        if ($job['children_count'] !== null) {
            $instance->setChildrenCount((int) $job['children_count']);
        }

        if ($job['result'] !== null) {
            $instance->setResult(unserialize($job['result']));
        }

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
