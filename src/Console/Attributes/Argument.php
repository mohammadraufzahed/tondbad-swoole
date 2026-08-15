<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Attributes;

use TondbadSwoole\Validation\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Argument
{
    public function __construct(
        public readonly string $name = '',
        public readonly int $mode = 0,
        public readonly string $description = '',
        public readonly mixed $default = null,
        public readonly string|Schema|null $schema = null,
        public readonly array $allowed = [],
    ) {
    }
}
