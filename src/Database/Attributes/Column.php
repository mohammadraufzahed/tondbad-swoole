<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly ?int $total = null,
        public readonly ?int $places = null,
        public readonly ?array $allowed = null,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly bool $unsigned = false,
        public readonly bool $unique = false,
        public readonly bool $index = false,
        public readonly bool $primary = false,
        public readonly bool $autoIncrement = false,
        public readonly ?string $comment = null,
    ) {
    }
}
