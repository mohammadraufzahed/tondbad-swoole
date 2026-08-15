<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Events;

use TondbadSwoole\Events\Event;

final class ConsoleEvent extends Event
{
    /**
     * @param list<string> $argv
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $command,
        public readonly array $argv,
        public readonly int $exitCode = 0,
        public readonly ?\Throwable $exception = null,
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'console.' . $this->type;
    }
}
