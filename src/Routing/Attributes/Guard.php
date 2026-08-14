<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

/**
 * @param class-string<\TondbadSwoole\Routing\Contracts\Guard> $guard
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Guard
{
    public function __construct(public readonly string $guard)
    {
    }
}
