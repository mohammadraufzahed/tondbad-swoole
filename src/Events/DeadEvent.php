<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

final class DeadEvent extends Event
{
    public function __construct(public readonly object $originalEvent)
    {
    }
}
