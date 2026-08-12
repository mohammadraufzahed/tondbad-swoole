<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Contracts\Authenticatable;

class GenericUser implements Authenticatable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly array $attributes,
        private readonly string $authIdentifierName = 'id',
        private readonly string $authPasswordName = 'password',
    ) {
    }

    public function getAuthIdentifier(): string|int|null
    {
        return $this->attributes[$this->authIdentifierName] ?? null;
    }

    public function getAuthIdentifierName(): string
    {
        return $this->authIdentifierName;
    }

    public function getAuthPassword(): ?string
    {
        $password = $this->attributes[$this->authPasswordName] ?? null;

        return is_string($password) ? $password : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
