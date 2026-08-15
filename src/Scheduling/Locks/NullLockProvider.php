<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Locks;

use TondbadSwoole\Scheduling\Contracts\LockProvider;

class NullLockProvider implements LockProvider
{
    public function acquire(string $key, int $timeoutMs = 0): bool
    {
        return true;
    }

    public function release(string $key): void
    {
    }
}
