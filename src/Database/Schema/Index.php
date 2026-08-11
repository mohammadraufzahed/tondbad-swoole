<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Schema;

class Index
{
    public function __construct(
        public string $name,
        public array $columns,
        public string $type,
        public ?string $algorithm = null,
    ) {
    }
}
