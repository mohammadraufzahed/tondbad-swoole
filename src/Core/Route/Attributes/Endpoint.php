<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Endpoint
{
    public function __construct(
        public readonly string $method,
        public readonly string $path
    )
    {
    }
}