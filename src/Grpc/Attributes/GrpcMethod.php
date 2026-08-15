<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class GrpcMethod
{
    public function __construct(
        public readonly string $name,
        public readonly string $input,
        public readonly string $output,
        public readonly bool $clientStreaming = false,
        public readonly bool $serverStreaming = false,
    ) {
    }
}
