<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

final class ProtoService
{
    /** @param ProtoMethod[] $methods */
    public function __construct(
        public readonly string $shortName,
        public readonly string $name,
        public readonly string $package,
        public readonly string $phpNamespace,
        public readonly array $methods,
    ) {
    }
}
