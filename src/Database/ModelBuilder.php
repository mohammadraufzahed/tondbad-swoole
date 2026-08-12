<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use TondbadSwoole\Database\Query\Builder;

class ModelBuilder extends Builder
{
    protected ?string $model = null;

    protected array $eagerLoad = [];

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function with(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ((array) $relations as $relation) {
            if (is_string($relation) && $relation !== '') {
                $this->eagerLoad[] = $relation;
            }
        }

        return $this;
    }

    public function newQuery(): self
    {
        return new static($this->connection, $this->grammar);
    }

    public function toBase(): Builder
    {
        $builder = new Builder($this->connection, $this->grammar);
        $builder->from = $this->from;
        $builder->columns = $this->columns;
        $builder->distinct = $this->distinct;
        $builder->wheres = $this->wheres;
        $builder->joins = $this->joins;
        $builder->groups = $this->groups;
        $builder->havings = $this->havings;
        $builder->orders = $this->orders;
        $builder->limit = $this->limit;
        $builder->offset = $this->offset;
        $builder->bindings = $this->bindings;

        return $builder;
    }

    public function get(): array
    {
        $rows = $this->toBase()->get();

        if ($this->model === null) {
            return $rows;
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->model::newFromBuilder($row);
        }

        return $this->eagerLoadRelations($models);
    }

    public function first(): mixed
    {
        $rows = $this->toBase()->limit(1)->get();

        if ($rows === [] || $this->model === null) {
            return $rows[0] ?? null;
        }

        $models = $this->eagerLoadRelations([$this->model::newFromBuilder($rows[0])]);

        return $models[0] ?? null;
    }

    public function find(mixed $id, array|string $columns = ['*']): mixed
    {
        if ($this->model === null) {
            return $this->toBase()->where('id', '=', $id)->select($columns)->first();
        }

        $instance = new $this->model();

        return $this->where($instance->getKeyName(), '=', $id)->select($columns)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->toBase()->first();

        return $row !== null ? ($row[$column] ?? null) : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->toBase()->get();
        $values = [];

        foreach ($results as $row) {
            $value = $row[$column] ?? null;

            if ($key !== null) {
                $values[$row[$key] ?? null] = $value;
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('count', $column);
    }

    public function aggregate(string $function, string $column): mixed
    {
        $this->columns = [$this->raw("{$function}({$this->wrapColumn($column)}) as aggregate")];

        $row = $this->toBase()->first();

        return $row !== null ? ($row['aggregate'] ?? null) : null;
    }

    public function loadRelations(array $models, array|string $relations): array
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        return $this->newQuery()
            ->setModel($this->model ?? static::class)
            ->from($this->from)
            ->with($relations)
            ->eagerLoadRelations($models);
    }

    protected function eagerLoadRelations(array $models): array
    {
        if ($models === [] || $this->eagerLoad === [] || $this->model === null) {
            return $models;
        }

        $relations = $this->eagerLoad;
        $this->eagerLoad = [];

        $this->eagerLoadRelationsFor($models, $relations);

        return $models;
    }

    /**
     * @param list<Model> $models
     * @param list<string> $relations
     */
    private function eagerLoadRelationsFor(array $models, array $relations): void
    {
        if ($models === [] || $relations === []) {
            return;
        }

        $groups = [];

        foreach ($relations as $relation) {
            $segments = explode('.', $relation, 2);
            $first = $segments[0];
            $rest = $segments[1] ?? null;

            $groups[$first][] = $rest;
        }

        foreach ($groups as $relation => $rests) {
            $this->eagerLoadRelation($models, $relation);

            $related = $this->relatedModels($models, $relation);

            if ($related === []) {
                continue;
            }

            $nested = array_values(array_filter($rests, fn (?string $r): bool => $r !== null));

            if ($nested === []) {
                continue;
            }

            $relatedModel = $related[0]::class;

            (new static($this->connection, $this->grammar))
                ->setModel($relatedModel)
                ->eagerLoadRelationsFor($related, $nested);
        }
    }

    protected function eagerLoadRelation(array $models, string $relation): void
    {
        $instance = new $this->model();

        if (!method_exists($instance, $relation)) {
            return;
        }

        $relationObj = $instance->$relation();

        if (!$relationObj instanceof Relations\Relation) {
            return;
        }

        $relationObj->addEagerConstraints($models);
        $results = $relationObj->getEager();
        $relationObj->match($models, $results);
    }

    /**
     * @param list<Model> $models
     * @return list<Model>
     */
    private function relatedModels(array $models, string $relation): array
    {
        $related = [];

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }

            $value = $model->getRelation($relation);

            if ($value instanceof Model) {
                $related[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Model) {
                        $related[] = $item;
                    }
                }
            }
        }

        return $related;
    }
}
