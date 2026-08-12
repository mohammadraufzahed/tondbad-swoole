<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\RateLimiter;

interface RateLimiterInterface
{
    public function tooManyAttempts(string $key, int $max, int $window): bool;

    public function availableIn(string $key, int $window): int;

    public function hit(string $key, int $window): void;
}
