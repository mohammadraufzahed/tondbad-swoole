<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Casts;

use TondbadSwoole\Database\Model;

interface CastsAttributes
{
    /**
     * Cast the given value from the database to a PHP value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed;

    /**
     * Cast the given PHP value for storage in the database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed;
}
