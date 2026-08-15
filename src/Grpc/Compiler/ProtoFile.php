<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

final class ProtoFile
{
    /** @param ProtoMessage[] $messages */
    /** @param ProtoService[] $services */
    public function __construct(
        public readonly string $name,
        public readonly ?string $package,
        public readonly string $phpNamespace,
        public readonly string $metadataNamespace,
        public readonly array $messages,
        public readonly array $services,
    ) {
    }
}
