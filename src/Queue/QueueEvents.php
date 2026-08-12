<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use Closure;

class QueueEvents
{
    /**
     * @var array<string, list<Closure>>
     */
    private array $listeners = [];

    public function on(string $event, Closure $callback): void
    {
        $this->listeners[$event][] = $callback;
    }

    public function emit(string $event, array $data = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}
