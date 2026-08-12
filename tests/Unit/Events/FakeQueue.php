<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Events;

use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueInterface;

class FakeQueue implements QueueInterface
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
}
