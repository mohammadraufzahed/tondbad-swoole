<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCommand
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $aliases = [],
        public readonly bool $coroutine = true,
    ) {
    }
}
