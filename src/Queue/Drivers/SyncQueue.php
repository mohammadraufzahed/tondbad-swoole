<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use Throwable;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Jobs\JobStatus;
use TondbadSwoole\Queue\Queue;
use TondbadSwoole\Queue\QueueEvents;
use TondbadSwoole\Queue\Worker;

class SyncQueue extends Queue
{
    public function __construct(
        private readonly Worker $worker,
        private readonly string $defaultQueue = 'default',
    ) {
        parent::__construct(new QueueEvents());
    }

    public function push(Job $job, ?string $queue = null): mixed
    {
        $this->worker->process($job);

        return null;
    }

    public function pop(?string $queue = null): ?Job
    {
        return null;
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    public function delete(int $id): bool
    {
        return true;
    }

    public function release(?int $id, int $delay = 0): bool
    {
        return true;
    }

    public function markCompleted(int $id): bool
    {
        return true;
    }

    public function markFailed(int $id, ?Throwable $exception = null): bool
    {
        return true;
    }

    public function progress(int $id, int $progress): bool
    {
        return true;
    }

    public function setResult(int $id, mixed $value): bool
    {
        return true;
    }

    public function getChildren(int $parentId): array
    {
        return [];
    }

    public function getJob(int $id): ?Job
    {
        return null;
    }

    public function getMetrics(?string $queue = null): array
    {
        return array_combine(
            array_map(fn (JobStatus $status) => $status->value, JobStatus::cases()),
            array_fill(0, count(JobStatus::cases()), 0)
        );
    }

    public function drain(?string $queue = null): int
    {
        return 0;
    }

    public function clean(int $gracePeriod = 86400, ?string $queue = null): int
    {
        return 0;
    }
}
