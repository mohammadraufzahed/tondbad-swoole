<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

final class HttpResponse
{
    /**
     * @param array<string, mixed> $json
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $json = [],
    ) {
    }
}
