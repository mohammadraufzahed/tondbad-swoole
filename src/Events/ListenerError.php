<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

final readonly class ListenerError
{
    public function __construct(
        public string $event,
        public string $listener,
        public \Throwable $exception,
    ) {
    }
}
