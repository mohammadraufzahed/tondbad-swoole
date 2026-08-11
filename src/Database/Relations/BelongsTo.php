<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;

class BelongsTo extends Relation
{
    public function __construct(
        Model $parent,
        string $related,
        protected string $foreignKey,
        protected string $ownerKey,
        string $relationName,
    ) {
        // For BelongsTo the "local" comparison key on the related table is the owner key.
        parent::__construct($parent, $related, $foreignKey, $ownerKey, $relationName);
    }

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
        $this->query->where($this->localKey, '=', $this->parent->getAttribute($this->foreignKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);

        if ($keys !== []) {
            $this->query->whereIn($this->localKey, $keys);
        }
    }

    public function match(array $models, array $results): void
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->localKey)] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $model->setRelation($this->relationName, $dictionary[$key] ?? null);
        }
    }
}
