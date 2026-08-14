<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Strategies;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;

class ApiKeyStrategy implements AuthStrategy
{
    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly string $key = 'api_key',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function authenticate(array $credentials): ?Authenticatable
    {
        return $this->provider->retrieveByCredentials([$this->key => $credentials[$this->key] ?? null]);
    }

    public function register(array $data): ?Authenticatable
    {
        return null;
    }
}
