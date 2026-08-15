<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Events;

class ConsoleEvent
{
    public function __construct(
        public readonly string $event,
        public readonly string $command,
        public readonly ?array $input = null,
        public readonly int $exitCode = 0,
        public readonly ?\Throwable $exception = null,
    ) {
    }

    public function name(): string
    {
        return 'console.' . $this->event;
    }
}
