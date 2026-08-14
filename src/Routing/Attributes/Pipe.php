<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

/**
 * @param class-string<\TondbadSwoole\Routing\Contracts\Pipe> $pipe
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Pipe
{
    /**
     * @param class-string<\TondbadSwoole\Routing\Contracts\Pipe> $pipe
     * @param array<string, mixed> $args
     */
    public function __construct(
        public readonly string $pipe,
        public readonly array $args = [],
    ) {
    }
}
