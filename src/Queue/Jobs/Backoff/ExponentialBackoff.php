<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs\Backoff;

class ExponentialBackoff implements BackoffStrategy
{
    public function __construct(
        private readonly int $delay,
        private readonly int $max = 0,
    ) {
    }

    public function delay(int $attempts): int
    {
        $delay = $this->delay * (2 ** max(0, $attempts - 1));

        if ($this->max > 0 && $delay > $this->max) {
            return $this->max;
        }

        return $delay;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function getTypeForStorage(): string
    {
        return 'exponential';
    }

    public function getValueForStorage(): int
    {
        return $this->delay;
    }
}
