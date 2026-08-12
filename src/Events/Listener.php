<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Listener
{
    /**
     * @param list<string> $events
     * @param bool $queued
     */
    public function __construct(
        public readonly array $events = [],
        public readonly bool $queued = false,
    ) {
    }
}
