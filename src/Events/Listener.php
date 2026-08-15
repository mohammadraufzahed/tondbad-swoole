<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Listener
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        public readonly array $events = [],
        public readonly ?int $priority = null,
        public readonly bool $queued = false,
        public readonly bool $async = false,
    ) {
    }
}
