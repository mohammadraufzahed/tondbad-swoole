<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Scopes;

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Query\Builder;

class SoftDeleteScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->getTable() . '.deleted_at');
    }
}
