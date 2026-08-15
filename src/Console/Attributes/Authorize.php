<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Authorize
{
    public function __construct(
        public readonly string $ability,
        public readonly ?string $guard = null,
    ) {
    }
}
