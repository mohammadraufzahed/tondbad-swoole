<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;

class MorphTo extends Relation
{
    protected string $morphType;

    public function __construct(Model $parent, string $name, ?string $type = null, ?string $id = null, ?string $ownerKey = null)
    {
        $type ??= $name . '_type';
        $id ??= $name . '_id';
        $ownerKey ??= (new MorphPlaceholder())->getKeyName();

        $this->morphType = $type;

        parent::__construct($parent, MorphPlaceholder::class, $id, $ownerKey, $name);
    }

    public function getResults(): mixed
    {
        $related = $this->relatedModel();

        if ($related === null) {
            return null;
        }

        return $related->newQuery()->where($this->localKey, '=', $this->parent->getAttribute($this->foreignKey))->first();
    }

    public function addConstraints(): void
    {
    }

    public function addEagerConstraints(array $models): void
    {
        $this->eagerGroups = [];

        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $key = $model->getAttribute($this->foreignKey);

            if ($type === null || $key === null) {
                continue;
            }

            $this->eagerGroups[$type][] = $key;
        }
    }

    public function getEager(): array
    {
        $results = [];

        foreach ($this->eagerGroups as $class => $keys) {
            if (!is_subclass_of($class, Model::class) && $class !== Model::class) {
                continue;
            }

            $related = new $class();
            $rows = $related->newQuery()->whereIn($this->localKey, $keys)->get();

            foreach ($rows as $row) {
                $results[] = $row;
            }
        }

        return $results;
    }

    public function match(array $models, array $results): void
    {
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $key = $model->getAttribute($this->foreignKey);
            $match = null;

            foreach ($results as $result) {
                if ($result instanceof $type && $result->getAttribute($this->localKey) == $key) {
                    $match = $result;

                    break;
                }
            }

            $model->setRelation($this->relationName, $match);
        }
    }

    public function addWhereHasConstraints(string $parentTable): void
    {
        throw new \LogicException('has()/whereHas() on morphTo relations are not supported.');
    }

    public function getWhereHasGroupByColumn(): string
    {
        throw new \LogicException('has()/whereHas() on morphTo relations are not supported.');
    }

    protected ?Model $relatedModel = null;

    protected function relatedModel(): ?Model
    {
        if ($this->relatedModel !== null) {
            return $this->relatedModel;
        }

        $type = $this->parent->getAttribute($this->morphType);

        if ($type === null || !is_subclass_of($type, Model::class) && $type !== Model::class) {
            return null;
        }

        $this->relatedModel = new $type();

        return $this->relatedModel;
    }

    /** @var array<string, list<mixed>> */
    protected array $eagerGroups = [];
}
