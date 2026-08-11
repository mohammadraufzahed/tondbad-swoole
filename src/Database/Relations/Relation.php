<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\ModelBuilder;

abstract class Relation
{
    protected ModelBuilder $query;

    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey,
        protected string $relationName,
    ) {
        $this->query = $this->newRelatedInstance()->newQuery();
    }

    abstract public function getResults(): mixed;

    abstract public function getEager(): array;

    abstract public function addConstraints(): void;

    abstract public function addEagerConstraints(array $models): void;

    abstract public function match(array $models, array $results): void;

    public function getQuery(): ModelBuilder
    {
        return $this->query;
    }

    protected function newRelatedInstance(): Model
    {
        return new $this->related();
    }

    protected function getKeys(array $models, string $key): array
    {
        $keys = array_values(
            array_unique(
                array_filter(
                    array_map(fn (Model $model) => $model->getAttribute($key), $models),
                    fn (mixed $value) => $value !== null,
                ),
            ),
        );
        sort($keys);

        return $keys;
    }
}
