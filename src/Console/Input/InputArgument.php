<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Input;

use TondbadSwoole\Validation\Schema;

final class InputArgument
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const IS_ARRAY = 4;

    public function __construct(
        public readonly string $name,
        public readonly int $mode = self::OPTIONAL,
        public readonly string $description = '',
        public readonly mixed $default = null,
        public readonly ?Schema $schema = null,
    ) {
    }

    public function isRequired(): bool
    {
        return (bool) ($this->mode & self::REQUIRED);
    }

    public function isArray(): bool
    {
        return (bool) ($this->mode & self::IS_ARRAY);
    }

    public function isDefaultValueAvailable(): bool
    {
        return $this->default !== null;
    }
}
