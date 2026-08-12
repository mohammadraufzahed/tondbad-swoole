<?php

declare(strict_types=1);

namespace TondbadSwoole\Http\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Authenticate
{
    public function __construct(
        public readonly ?string $guard = null,
    ) {
    }
}
