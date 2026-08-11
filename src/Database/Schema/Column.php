<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Schema;

class Column
{
    public bool $nullable = false;

    public bool $unsigned = false;

    public bool $autoIncrement = false;

    public bool $unique = false;

    public bool $index = false;

    public bool $primary = false;

    public mixed $default = null;

    public bool $useCurrent = false;

    public ?string $collation = null;

    public ?string $charset = null;

    public ?string $comment = null;

    public ?string $after = null;

    public bool $first = false;

    public ?int $length = null;

    public ?int $total = null;

    public ?int $places = null;

    public ?int $precision = null;

    public ?array $allowed = null;

    public string $position = '';

    public ?string $renameFrom = null;

    public bool $change = false;

    public function __construct(
        public string $type,
        public string $name,
    ) {
    }
}
