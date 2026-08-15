<?php

declare(strict_types=1);

namespace TondbadSwoole\GRPC\Events;

use OpenSwoole\GRPC\MessageInterface;
use TondbadSwoole\Events\Event;

final class GrpcEvent extends Event
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly MessageInterface $message,
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'grpc.' . $this->type;
    }
}
