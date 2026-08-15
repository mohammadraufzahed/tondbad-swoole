<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Events;

use TondbadSwoole\Events\Event;

final class QueueEvent extends Event
{
    public function __construct(
        public readonly string $queueEvent,
        public readonly ?string $queue,
        public readonly array $data,
    ) {
    }

    public function name(): string
    {
        return match ($this->queueEvent) {
            'added', 'delayed', 'waiting', 'active', 'stalled', 'completed', 'failed', 'released', 'progress' => 'queue.job.' . $this->queueEvent,
            'flow_added' => 'queue.flow.added',
            'rate_limited' => 'queue.rate_limited',
            default => 'queue.' . $this->queueEvent,
        };
    }
}
