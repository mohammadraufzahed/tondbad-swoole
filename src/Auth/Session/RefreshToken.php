<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Session;

final class RefreshToken
{
    public function __construct(
        public readonly string $value,
        public readonly string $sessionId,
        public readonly string $family,
        public readonly int $expiresAt,
    ) {
    }
}
