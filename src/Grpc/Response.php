<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class Response
{
    public function __construct(
        public readonly object $message,
        public readonly Status $status,
        public readonly ?Metadata $metadata = null,
    ) {
    }
}
