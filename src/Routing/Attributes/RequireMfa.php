<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class RequireMfa
{
    public function __construct(public readonly ?string $guard = null)
    {
    }
}
