<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Input;

use TondbadSwoole\Validation\Schema;

final class InputOption
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;
    public const VALUE_IS_ARRAY = 8;
    public const VALUE_NEGATABLE = 16;

    public function __construct(
        public readonly string $name,
        public readonly ?string $shortcut = null,
        public readonly int $mode = self::VALUE_NONE,
        public readonly string $description = '',
        public readonly mixed $default = null,
        public readonly ?Schema $schema = null,
    ) {
    }

    public function acceptsValue(): bool
    {
        return $this->isValueRequired() || $this->isValueOptional();
    }

    public function isValueRequired(): bool
    {
        return (bool) ($this->mode & self::VALUE_REQUIRED);
    }

    public function isValueOptional(): bool
    {
        return (bool) ($this->mode & self::VALUE_OPTIONAL);
    }

    public function isArray(): bool
    {
        return (bool) ($this->mode & self::VALUE_IS_ARRAY);
    }

    public function isNegatable(): bool
    {
        return (bool) ($this->mode & self::VALUE_NEGATABLE);
    }

    public function isValueNone(): bool
    {
        return !$this->acceptsValue();
    }
}
