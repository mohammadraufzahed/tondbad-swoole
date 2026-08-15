<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Locks;

use Predis\Client;
use TondbadSwoole\Scheduling\Contracts\LockProvider;

class RedisLockProvider implements LockProvider
{
    private readonly string $token;

    public function __construct(
        private readonly Client $redis,
        private readonly string $prefix = 'tondbad:schedule:lock',
    ) {
        $this->token = bin2hex(random_bytes(8));
    }

    public function acquire(string $key, int $timeoutMs = 0): bool
    {
        $lockKey = "{$this->prefix}:{$key}";
        $expiresMs = max($timeoutMs, 60000);

        if ($timeoutMs <= 0) {
            return $this->redis->set($lockKey, $this->token, 'PX', $expiresMs, 'NX') !== null;
        }

        $start = microtime(true) * 1000;

        while ((microtime(true) * 1000) - $start < $timeoutMs) {
            if ($this->redis->set($lockKey, $this->token, 'PX', $expiresMs, 'NX') !== null) {
                return true;
            }

            usleep(10000);
        }

        return false;
    }

    public function release(string $key): void
    {
        $lockKey = "{$this->prefix}:{$key}";

        if ((string) $this->redis->get($lockKey) === $this->token) {
            $this->redis->del([$lockKey]);
        }
    }
}
