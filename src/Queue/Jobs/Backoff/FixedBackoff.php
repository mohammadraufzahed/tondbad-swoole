<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs\Backoff;

class FixedBackoff implements BackoffStrategy
{
    public function __construct(
        private readonly int $delay,
    ) {
    }

    public function delay(int $attempts): int
    {
        return $this->delay;
    }

    public function getTypeForStorage(): string
    {
        return 'fixed';
    }

    public function getValueForStorage(): int
    {
        return $this->delay;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
