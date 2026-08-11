<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use TondbadSwoole\Queue\Jobs\Job;

interface QueueInterface
{
    public function push(Job $job, ?string $queue = null): mixed;

    public function pop(?string $queue = null): ?Job;

    public function size(?string $queue = null): int;

    public function delete(int $id): bool;

    public function release(?int $id, int $delay = 0): bool;
}
