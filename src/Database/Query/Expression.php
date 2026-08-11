<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query;

class Expression
{
    public function __construct(protected readonly string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
