<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Events;

use TondbadSwoole\Events\Event;

final class OrmEvent extends Event
{
    public function __construct(
        public readonly string $type,
        public readonly object $entity,
    ) {
    }

    public function name(): string
    {
        return 'orm.' . $this->type;
    }
}
