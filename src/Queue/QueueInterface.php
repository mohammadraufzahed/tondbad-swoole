<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use Closure;
use Throwable;
use TondbadSwoole\Queue\Jobs\Job;

interface QueueInterface
{
    public function push(Job $job, ?string $queue = null): mixed;

    public function pop(?string $queue = null): ?Job;

    public function size(?string $queue = null): int;

    public function delete(int $id): bool;

    public function release(?int $id, int $delay = 0): bool;

    public function markCompleted(int $id): bool;

    public function markFailed(int $id, ?Throwable $exception = null): bool;

    public function progress(int $id, int $progress): bool;

    public function setResult(int $id, mixed $value): bool;

    public function getChildren(int $parentId): array;

    public function add(Job $job, ?string $queue = null, array $options = []): mixed;

    public function addBulk(array $jobs, ?string $queue = null, array $options = []): array;

    public function getJob(int $id): ?Job;

    public function drain(?string $queue = null): int;

    public function clean(int $gracePeriod = 86400, ?string $queue = null): int;

    public function pause(?string $queue = null): void;

    public function resume(?string $queue = null): void;

    public function getMetrics(?string $queue = null): array;

    public function on(string $event, Closure $callback): void;

    public function emit(string $event, array $data = []): void;
}
