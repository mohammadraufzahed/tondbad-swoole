<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Embedded
{
    public function __construct(
        public readonly string $class,
        public readonly string $prefix = '',
    ) {
    }
}
