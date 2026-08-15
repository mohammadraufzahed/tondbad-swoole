<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

final readonly class ListenerId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
