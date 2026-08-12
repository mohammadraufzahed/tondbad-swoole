<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

class EntityEvent
{
    public function __construct(
        public readonly string $event,
        public readonly object $entity,
    ) {
    }
}
