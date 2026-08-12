<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public readonly string $name)
    {
    }
}
