<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class MethodDescriptor
{
    /** @param ?\Closure(Request): Response $handler */
    public function __construct(
        public readonly string $name,
        public readonly string $inputClass,
        public readonly string $outputClass,
        public readonly bool $clientStreaming = false,
        public readonly bool $serverStreaming = false,
        public readonly ?\Closure $handler = null,
    ) {
    }
}
