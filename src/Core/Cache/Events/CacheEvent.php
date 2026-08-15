<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache\Events;

use TondbadSwoole\Events\Event;

final class CacheEvent extends Event
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly mixed $value = null,
        public readonly array $tags = [],
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'cache.' . $this->type;
    }
}
