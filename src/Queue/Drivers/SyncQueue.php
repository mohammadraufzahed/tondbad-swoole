<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\Worker;

class SyncQueue implements QueueInterface
{
    public function __construct(
        private readonly Worker $worker,
        private readonly string $defaultQueue = 'default',
    ) {
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
}
