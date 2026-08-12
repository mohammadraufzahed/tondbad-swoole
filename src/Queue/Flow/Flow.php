<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Flow;

use TondbadSwoole\Queue\Jobs\Job;

class Flow
{
    /**
     * @param list<FlowChild> $children
     */
    public function __construct(
        public readonly Job $job,
        public readonly array $children = [],
        public readonly ?string $queue = null,
    ) {
    }

    /**
     * @param list<FlowChild> $children
     */
    public static function create(Job $job, array $children = [], ?string $queue = null): self
    {
        return new self($job, $children, $queue);
    }
}
