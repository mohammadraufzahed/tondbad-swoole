<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Events;

use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Jobs\JobStatus;
use TondbadSwoole\Queue\Queue;

class FakeQueue extends Queue
{
    /**
     * @var list<Job>
     */
    public array $jobs = [];

    public function push(Job $job, ?string $queue = null): mixed
    {
        $this->jobs[] = $job;

        return $job;
    }

    public function pop(?string $queue = null): ?Job
    {
        return array_shift($this->jobs);
    }

    public function size(?string $queue = null): int
    {
        return count($this->jobs);
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

    public function markFailed(int $id): bool
    {
        return true;
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
        $count = count($this->jobs);
        $this->jobs = [];

        return $count;
    }

    public function clean(int $gracePeriod = 86400, ?string $queue = null): int
    {
        return 0;
    }
}
