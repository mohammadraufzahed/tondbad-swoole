<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Param
{
    /**
     * @param list<mixed> $values
     */
    public function __construct(public readonly array $values)
    {
    }
}
