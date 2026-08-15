<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

final class ProtoMethod
{
    public function __construct(
        public readonly string $name,
        public readonly string $inputType,
        public readonly string $outputType,
        public readonly string $inputPhpClass,
        public readonly string $outputPhpClass,
        public readonly bool $clientStreaming,
        public readonly bool $serverStreaming,
    ) {
    }
}
