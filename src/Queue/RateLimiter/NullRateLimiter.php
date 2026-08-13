<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\RateLimiter;

class NullRateLimiter implements RateLimiterInterface
{
    public function tooManyAttempts(string $key, int $max, int $window): bool
    {
        return false;
    }

    public function availableIn(string $key, int $window): int
    {
        return 0;
    }

    public function hit(string $key, int $window): void
    {
    }

    public function attempt(string $key, int $max, int $window): bool
    {
        return true;
    }
}
