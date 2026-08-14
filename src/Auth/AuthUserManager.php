<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Support\Hash\Contracts\Hasher;

class AuthUserManager
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly Config $config,
        private readonly Hasher $hasher,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?string $providerName = null): Authenticatable
    {
        $providerName ??= (string) $this->config->get('auth.defaults.provider', 'users');
        $providerConfig = $this->config->get("auth.providers.{$providerName}");

        if (!is_array($providerConfig)) {
            throw new \InvalidArgumentException("Auth provider [{$providerName}] is not defined.");
        }

        $driver = $providerConfig['driver'] ?? 'database';

        if ($driver === 'eloquent') {
            return $this->createEloquentUser($providerConfig['model'] ?? '', $data);
        }

        return $this->createDatabaseUser($providerConfig['table'] ?? 'users', $data, $providerConfig);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createDatabaseUser(string $table, array $data, array $providerConfig): GenericUser
    {
        if (isset($data['password']) && is_string($data['password'])) {
            $data['password'] = $this->hasher->make($data['password']);
        }

        $id = $this->databaseManager->table($table)->insertGetId($data);
        $row = $this->databaseManager->table($table)->where('id', '=', $id)->first();

        if ($row === null) {
            throw new \RuntimeException('User was created but could not be retrieved.');
        }

        return new GenericUser(
            $table,
            $row,
            $providerConfig['auth_identifier'] ?? 'id',
            $providerConfig['auth_password'] ?? 'password',
        );
    }

    /**
     * @param class-string $model
     * @param array<string, mixed> $data
     */
    private function createEloquentUser(string $model, array $data): Authenticatable
    {
        if ($model === '' || !class_exists($model) || !is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException('Auth provider model is not configured.');
        }

        if (isset($data['password']) && is_string($data['password'])) {
            $data['password'] = $this->hasher->make($data['password']);
        }

        /** @var \TondbadSwoole\Database\Model $user */
        $user = new $model();
        $user->forceFill($data);

        if (!$user->save()) {
            throw new \RuntimeException('User could not be created.');
        }

        return $user;
    }
}
