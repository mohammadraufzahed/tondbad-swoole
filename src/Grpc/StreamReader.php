<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class StreamReader
{
    /** @param list<object> $messages */
    public function __construct(private array $messages)
    {
    }

    private int $index = 0;

    public function recv(): ?object
    {
        return $this->messages[$this->index++] ?? null;
    }

    /** @return list<object> */
    public function all(): array
    {
        return $this->messages;
    }
}
