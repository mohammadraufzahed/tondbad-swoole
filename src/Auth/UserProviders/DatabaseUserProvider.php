<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\UserProviders;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Database\DatabaseManager;

class DatabaseUserProvider implements UserProvider
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly string $table,
        private readonly string $authIdentifierName = 'id',
        private readonly string $authPasswordName = 'password',
    ) {
    }

    public function retrieveById(string|int $id): ?Authenticatable
    {
        $row = $this->databaseManager->connection()->table($this->table)
            ->where($this->authIdentifierName, '=', $id)
            ->first();

        return $row === null ? null : new GenericUser($row, $this->authIdentifierName, $this->authPasswordName);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $query = $this->databaseManager->connection()->table($this->table);

        foreach ($credentials as $key => $value) {
            if ($key === $this->authPasswordName) {
                continue;
            }

            $query->where($key, '=', $value);
        }

        $row = $query->first();

        return $row === null ? null : new GenericUser($row, $this->authIdentifierName, $this->authPasswordName);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials[$this->authPasswordName] ?? null;

        if (!is_string($password) || $password === '') {
            return false;
        }

        $hash = $user->getAuthPassword();

        return $hash !== null && password_verify($password, $hash);
    }
}
