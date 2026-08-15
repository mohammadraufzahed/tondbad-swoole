<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

final class GenericEvent extends Event
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    public function __construct(
        private readonly string $name,
        private readonly mixed $payload = null,
        private readonly mixed $subject = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function subject(): mixed
    {
        return $this->subject;
    }

    public function setArgument(string $key, mixed $value): static
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    public function getArgument(string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
