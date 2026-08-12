<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Flow;

use TondbadSwoole\Queue\Jobs\Job;

class FlowChild
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly Job $job,
        public readonly array $options = [],
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function create(Job $job, array $options = []): self
    {
        return new self($job, $options);
    }
}
