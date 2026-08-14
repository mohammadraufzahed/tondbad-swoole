<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Strategies;

use TondbadSwoole\Auth\AuthUserManager;
use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;

class EmailPasswordStrategy implements AuthStrategy
{
    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly AuthUserManager $userManager,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function authenticate(array $credentials): ?Authenticatable
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
            return null;
        }

        return $user;
    }

    public function register(array $data): ?Authenticatable
    {
        if ($data === []) {
            return null;
        }

        return $this->userManager->create($data);
    }
}
