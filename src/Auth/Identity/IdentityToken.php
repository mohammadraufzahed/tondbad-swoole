<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

final class IdentityToken
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly array $claims = [],
    ) {
    }
}
