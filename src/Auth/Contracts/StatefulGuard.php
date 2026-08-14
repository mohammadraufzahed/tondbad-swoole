<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Contracts;

use TondbadSwoole\Auth\Session\AuthSession;

interface StatefulGuard extends Guard
{
    /**
     * @param array<string, mixed> $claims
     */
    public function login(Authenticatable $user, array $claims = []): AuthSession;

    public function logout(): void;
}
