<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

/**
 * @param class-string<\TondbadSwoole\Routing\Contracts\Interceptor> $interceptor
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Interceptor
{
    public function __construct(public readonly string $interceptor)
    {
    }
}
