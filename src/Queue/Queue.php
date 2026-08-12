<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use Closure;
use TondbadSwoole\Queue\Jobs\Job;

abstract class Queue implements QueueInterface
{
    public function add(Job $job, ?string $queue = null, array $options = []): mixed
    {
        if ($queue !== null) {
            $job->onQueue($queue);
        }

        if ($options !== []) {
            $job->setOptions($options);
        }

        return $this->push($job, $job->getQueue() ?? $queue);
    }

    public function addBulk(array $jobs, ?string $queue = null, array $options = []): array
    {
        $ids = [];

        foreach ($jobs as $job) {
            if ($job instanceof Job) {
                $ids[] = $this->add($job, $queue, $options);
                continue;
            }

            if (is_array($job) && isset($job['job']) && $job['job'] instanceof Job) {
                $ids[] = $this->add($job['job'], $queue, array_merge($options, $job['options'] ?? []));
                continue;
            }
        }

        return $ids;
    }

    public function pause(?string $queue = null): void
    {
    }

    public function resume(?string $queue = null): void
    {
    }

    public function on(string $event, Closure $callback): void
    {
    }

    abstract public function push(Job $job, ?string $queue = null): mixed;

    abstract public function pop(?string $queue = null): ?Job;

    abstract public function size(?string $queue = null): int;

    abstract public function delete(int $id): bool;

    abstract public function release(?int $id, int $delay = 0): bool;

    abstract public function markCompleted(int $id): bool;

    abstract public function markFailed(int $id): bool;

    abstract public function getJob(int $id): ?Job;

    abstract public function getMetrics(?string $queue = null): array;

    abstract public function drain(?string $queue = null): int;

    abstract public function clean(int $gracePeriod = 86400, ?string $queue = null): int;
}
