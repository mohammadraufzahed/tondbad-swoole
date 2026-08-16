<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\ModelBuilder;
use TondbadSwoole\Database\Query\Builder;

abstract class Relation
{
    protected ModelBuilder $query;

    protected array $cascade = [];

    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey,
        protected string $relationName,
    ) {
        $this->query = $this->newRelatedInstance()->newQuery();
    }

    public function getCascade(): array
    {
        return $this->cascade;
    }

    public function setCascade(array $cascade): self
    {
        $this->cascade = $cascade;

        return $this;
    }

    public function getRelated(): Model
    {
        return $this->newRelatedInstance();
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getRelationName(): string
    {
        return $this->relationName;
    }

    abstract public function getResults(): mixed;

    abstract public function getEager(): array;

    abstract public function addConstraints(): void;

    abstract public function addEagerConstraints(array $models): void;

    abstract public function match(array $models, array $results): void;

    /**
     * Optional override for relations that cannot be expressed by the generic
     * existence query (e.g. morphTo, which needs an OR group per type).
     * Return true when the parent query has been modified directly.
     */
    public function addHasExistenceQuery(
        ModelBuilder $parent,
        string $parentTable,
        string $operator,
        int $count,
        string $boolean,
        bool $not,
        ?\Closure $callback = null,
    ): bool {
        return false;
    }

    public function getQuery(): ModelBuilder
    {
        return $this->query;
    }

    abstract public function addWhereHasConstraints(string $parentTable): void;

    public function getWhereHasGroupByColumn(): string
    {
        return $this->newRelatedInstance()->getTable() . '.' . $this->foreignKey;
    }

    /**
     * Optional override for relations that need a different existence query
     * (e.g. many-to-many counts pivot rows instead of related rows).
     */
    public function getRelationExistenceQueryForParent(ModelBuilder $parent, string $parentTable): ?Builder
    {
        return null;
    }

    protected function getWhereHasColumns(string $parentTable): array
    {
        return [
            $this->newRelatedInstance()->getTable() . '.' . $this->foreignKey,
            $parentTable . '.' . $this->localKey,
        ];
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
