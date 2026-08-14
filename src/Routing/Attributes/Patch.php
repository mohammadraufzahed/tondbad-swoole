<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Patch implements RouteMethod
{
    public function __construct(
        private readonly string $path = '',
        private readonly ?string $name = null,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function httpMethod(): string
    {
        return 'PATCH';
    }
}
