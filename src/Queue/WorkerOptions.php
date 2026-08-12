<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

class WorkerOptions
{
    /**
     * @param array<string, mixed>|null $rateLimiter
     */
    public function __construct(
        public readonly int $concurrency = 1,
        public readonly int $maxTries = 1,
        public readonly int $sleep = 3,
        public readonly ?int $timeout = null,
        public readonly ?array $rateLimiter = null,
        public readonly ?int $maxJobs = null,
        public readonly bool $stopWhenEmpty = false,
    ) {
    }
}
