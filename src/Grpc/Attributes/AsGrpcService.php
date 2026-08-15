<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsGrpcService
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $package = null,
    ) {
    }
}
