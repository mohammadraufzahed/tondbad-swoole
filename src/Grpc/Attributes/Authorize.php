<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Authorize
{
    public function __construct(public readonly string $ability)
    {
    }
}
