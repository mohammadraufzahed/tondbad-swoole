<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Events;

use TondbadSwoole\Events\Event;

final class ScheduleEvent extends Event
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $task,
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'schedule.' . $this->type;
    }
}
