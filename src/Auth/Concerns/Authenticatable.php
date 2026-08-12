<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Concerns;

use TondbadSwoole\Auth\Contracts\Authenticatable as AuthenticatableContract;

trait Authenticatable
{
    protected string $authPasswordName = 'password';

    public function getAuthIdentifier(): string|int|null
    {
        $key = $this->getKey();

        return is_string($key) || is_int($key) ? $key : null;
    }

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthPasswordName(): string
    {
        return $this->authPasswordName;
    }

    public function getAuthPassword(): ?string
    {
        $password = $this->getAttribute($this->authPasswordName);

        return is_string($password) ? $password : null;
    }
}
