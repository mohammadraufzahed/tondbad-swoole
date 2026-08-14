<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Authorize
{
    /**
     * @param array<int, mixed> $arguments
     */
    public function __construct(
        public readonly string $ability,
        public readonly array $arguments = [],
        public readonly ?string $guard = null,
    ) {
    }
}
