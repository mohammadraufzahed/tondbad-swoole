<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\UserProviders;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\UserProvider;

class EloquentUserProvider implements UserProvider
{
    /**
     * @param class-string<Authenticatable> $model
     */
    public function __construct(
        private readonly string $model,
    ) {
    }

    public function retrieveById(string|int $id): ?Authenticatable
    {
        $model = $this->model;

        if (!class_exists($model) || !is_subclass_of($model, \TondbadSwoole\Database\Model::class) && !is_subclass_of($model, Authenticatable::class)) {
            return null;
        }

        return $model::find($id);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $model = $this->model;

        if (!class_exists($model) || !is_subclass_of($model, \TondbadSwoole\Database\Model::class) && !is_subclass_of($model, Authenticatable::class)) {
            return null;
        }

        $conditions = [];

        foreach ($credentials as $key => $value) {
            if ($key === 'password') {
                continue;
            }

            $conditions[$key] = $value;
        }

        return $model::firstWhere($conditions);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (!is_string($password) || $password === '') {
            return false;
        }

        $hash = $user->getAuthPassword();

        return $hash !== null && password_verify($password, $hash);
    }
}
