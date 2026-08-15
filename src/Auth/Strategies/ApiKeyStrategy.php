<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Strategies;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Validation\Schema;

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
        $schema = Schema::object([
            $this->key => Schema::string()->required()->min(1),
        ])->lax();

        $result = $schema->safeParse($credentials);

        if (!$result->valid) {
            return null;
        }

        return $this->provider->retrieveByCredentials([$this->key => $result->data[$this->key]]);
    }

    public function register(array $data): ?Authenticatable
    {
        return null;
    }
}
