<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;

class Pivot extends Model
{
    public bool $timestamps = false;

    protected ?string $table = null;

    public static function fromAttributes(Model $parent, string $table, array $attributes, bool $exists = false): self
    {
        $pivot = new self();
        $pivot->setTable($table);
        $pivot->setRawAttributes($attributes, true);
        $pivot->exists = $exists;

        return $pivot;
    }
}
