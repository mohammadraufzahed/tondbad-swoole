<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Contracts;

interface LockProvider
{
    /**
     * Try to acquire a lock for the given key.
     *
     * @param int $timeoutMs maximum time to wait for the lock in milliseconds
     */
    public function acquire(string $key, int $timeoutMs = 0): bool;

    /**
     * Release a previously acquired lock.
     */
    public function release(string $key): void;
}
