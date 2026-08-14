<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Strategies;

use TondbadSwoole\Auth\AuthUserManager;
use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Validation\Schema;

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
        $schema = Schema::object([
            'email' => Schema::string()->email()->required(),
            'password' => Schema::string()->required(),
        ])->lax();

        $result = $schema->safeParse($credentials);

        if (!$result->valid) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials($result->data);

        if ($user === null || !$this->provider->validateCredentials($user, $result->data)) {
            return null;
        }

        return $user;
    }

    public function register(array $data): ?Authenticatable
    {
        $schema = Schema::object([
            'email' => Schema::string()->email()->required(),
            'password' => Schema::string()->min(8)->required(),
        ])->lax();

        $result = $schema->safeParse($data);

        if (!$result->valid) {
            return null;
        }

        return $this->userManager->create($result->data);
    }
}
