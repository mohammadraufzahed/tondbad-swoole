<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Concerns\Authenticatable;
use TondbadSwoole\Auth\Contracts\Authenticatable as AuthenticatableContract;
use TondbadSwoole\Database\Model;

/**
 * A generic Authenticatable user object backed by the ORM Model.
 *
 * This is used by DatabaseUserProvider to wrap a plain database row in an
 * entity that still benefits from Model attribute access and casting.
 */
class GenericUser extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public bool $timestamps = false;

    public function __construct(
        string $table,
        array $attributes,
        string $authIdentifierName = 'id',
        string $authPasswordName = 'password',
    ) {
        $this->table = $table;
        $this->primaryKey = $authIdentifierName;
        $this->authPasswordName = $authPasswordName;

        parent::__construct();

        $this->forceFill($attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getAttribute($key) ?? $default;
    }
}
