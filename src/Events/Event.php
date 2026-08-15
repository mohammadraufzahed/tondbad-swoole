<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

class Event
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function name(): string
    {
        return static::class;
    }
}
