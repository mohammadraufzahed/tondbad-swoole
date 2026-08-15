<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;

class MorphMany extends HasMany
{
    public function __construct(
        Model $parent,
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
    ) {
        $instance = new $related();
        $type ??= $name . '_type';
        $id ??= $name . '_id';
        $localKey ??= $parent->getKeyName();

        $this->morphType = $type;
        $this->morphClass = $parent->getMorphClass();

        parent::__construct($parent, $related, $id, $localKey, $name);
    }

    protected string $morphType;

    protected string $morphClass;

    public function addConstraints(): void
    {
        parent::addConstraints();

        $this->query->where($this->morphType, '=', $this->morphClass);
    }

    public function addEagerConstraints(array $models): void
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->morphType, '=', $this->morphClass);
    }

    public function match(array $models, array $results): void
    {
        $dictionary = [];
        foreach ($results as $result) {
            if ($result->getAttribute($this->morphType) !== $this->morphClass) {
                continue;
            }

            $dictionary[$this->getRelationKey($result)][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($this->relationName, $dictionary[$key] ?? []);
        }
    }

    public function addWhereHasConstraints(string $parentTable): void
    {
        [$related, $parent] = $this->getWhereHasColumns($parentTable);
        $this->query->where($this->morphType, '=', $this->morphClass);
        $this->query->whereColumn($related, '=', $parent);
    }

    public function getWhereHasGroupByColumn(): string
    {
        return $this->newRelatedInstance()->getTable() . '.' . $this->foreignKey;
    }

    public function getRelationExistenceQueryForParent(\TondbadSwoole\Database\ModelBuilder $parent, string $parentTable): ?\TondbadSwoole\Database\Query\Builder
    {
        return $parent->toBase()
            ->from($this->newRelatedInstance()->getTable())
            ->select($parent->raw('1'))
            ->whereColumn($this->newRelatedInstance()->getTable() . '.' . $this->foreignKey, '=', $parentTable . '.' . $this->localKey)
            ->where($this->morphType, '=', $this->morphClass);
    }
}
