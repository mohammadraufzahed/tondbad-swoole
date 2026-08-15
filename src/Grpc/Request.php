<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class Request
{
    public function __construct(
        public readonly string $service,
        public readonly string $method,
        public readonly object $message,
        public readonly Metadata $metadata,
        public readonly Context $context,
        public readonly ?\DateTimeImmutable $deadline = null,
    ) {
    }
}
