<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Failed;

use Throwable;
use TondbadSwoole\Queue\Jobs\Job;

interface FailedJobProviderInterface
{
    public function log(Job $job, Throwable $exception): mixed;

    public function find(int $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function forQueue(string $queue): array;

    public function delete(int $id): bool;

    public function flush(?string $queue = null): int;
}
