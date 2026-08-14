<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Session;

final class AuthSession
{
    public function __construct(
        public readonly Session $session,
        public readonly AccessToken $accessToken,
        public readonly ?RefreshToken $refreshToken = null,
    ) {
    }
}
