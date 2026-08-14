<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Session;

final class AccessToken
{
    public function __construct(
        public readonly string $value,
        public readonly string $sessionId,
        public readonly int $expiresAt,
        public readonly array $claims = [],
    ) {
    }
}
