<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

final class ProtoMessage
{
    public function __construct(
        public readonly string $name,
        public readonly string $phpNamespace,
    ) {
    }
}
