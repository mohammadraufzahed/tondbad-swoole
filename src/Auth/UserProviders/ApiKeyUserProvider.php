<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\UserProviders;

use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Database\DatabaseManager;

/**
 * User provider for API keys stored in a separate table.
 *
 * This allows a single user to own many API keys while keeping the
 * guard itself storage-agnostic.
 */
class ApiKeyUserProvider implements UserProvider
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly string $usersTable = 'users',
        private readonly string $apiKeysTable = 'api_keys',
        private readonly string $keyColumn = 'key',
        private readonly string $userIdColumn = 'user_id',
        private readonly ?string $expiresAtColumn = null,
        private readonly string $authIdentifierName = 'id',
        private readonly string $authPasswordName = 'password',
    ) {
    }

    public function retrieveById(string|int $id): ?Authenticatable
    {
        $row = $this->databaseManager->connection()->table($this->usersTable)
            ->where($this->authIdentifierName, '=', $id)
            ->first();

        return $row === null ? null : new GenericUser($this->usersTable, $row, $this->authIdentifierName, $this->authPasswordName);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $key = $this->extractKey($credentials);

        if ($key === null || $key === '') {
            return null;
        }

        $query = $this->databaseManager->connection()->table($this->apiKeysTable)
            ->where($this->keyColumn, '=', $key);

        if ($this->expiresAtColumn !== null) {
            $query->where(function ($q) {
                $q->whereNull($this->expiresAtColumn)
                    ->orWhere($this->expiresAtColumn, '>', date('Y-m-d H:i:s'));
            });
        }

        $keyRow = $query->first();

        if ($keyRow === null) {
            return null;
        }

        $userId = $keyRow[$this->userIdColumn] ?? null;

        if ($userId === null) {
            return null;
        }

        return $this->retrieveById($userId);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $key = $this->extractKey($credentials);

        if ($key === null || $key === '') {
            return false;
        }

        $query = $this->databaseManager->connection()->table($this->apiKeysTable)
            ->where($this->keyColumn, '=', $key)
            ->where($this->userIdColumn, '=', $user->getAuthIdentifier());

        if ($this->expiresAtColumn !== null) {
            $query->where(function ($q) {
                $q->whereNull($this->expiresAtColumn)
                    ->orWhere($this->expiresAtColumn, '>', date('Y-m-d H:i:s'));
            });
        }

        return $query->first() !== null;
    }

    private function extractKey(array $credentials): ?string
    {
        $key = $credentials[$this->keyColumn]
            ?? $credentials['api_key']
            ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }
}
