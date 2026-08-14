<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

interface Lock
{
    /**
     * Try to acquire a lock for the given key.
     *
     * @param int $timeoutMs maximum time to wait for the lock
     */
    public function acquire(string $key, int $timeoutMs): bool;

    /**
     * Release a previously acquired lock.
     */
    public function release(string $key): void;
}
