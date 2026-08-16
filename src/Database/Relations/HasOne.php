<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\ModelBuilder;

class HasOne extends Relation
{
    public function getResults(): mixed
    {
        $this->addConstraints();

        return $this->query->first();
    }

    public function getEager(): array
    {
        return $this->query->get();
    }

    public function addConstraints(): void
    {
        $this->query->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);

        if ($keys !== []) {
            $this->query->whereIn($this->foreignKey, $keys);
        }
    }

    public function match(array $models, array $results): void
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$this->getRelationKey($result)] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($this->relationName, $dictionary[$key] ?? null);
        }
    }

    protected function getRelationKey(Model $model): mixed
    {
        return $model->getAttribute($this->foreignKey);
    }

    public function getRelationExistenceQuery(ModelBuilder $parent, string $parentTable): ModelBuilder
    {
        $table = $this->newRelatedInstance()->getTable();

        return $this->query->newQuery()
            ->from($table)
            ->whereColumn($table . '.' . $this->foreignKey, '=', $parentTable . '.' . $this->localKey);
    }

    public function getHasGroupByColumn(): string
    {
        return $this->newRelatedInstance()->getTable() . '.' . $this->foreignKey;
    }
}
