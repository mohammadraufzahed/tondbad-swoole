<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class Context
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    public function withValue(string $key, mixed $value): self
    {
        $values = $this->values;
        $values[$key] = $value;

        return new self($values);
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getValues(): array
    {
        return $this->values;
    }
}
