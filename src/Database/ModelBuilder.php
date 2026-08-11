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

    protected function eagerLoadRelations(array $models): array
    {
        if ($models === [] || $this->eagerLoad === [] || $this->model === null) {
            return $models;
        }

        foreach ($this->eagerLoad as $relation) {
            $this->eagerLoadRelation($models, $relation);
        }

        return $models;
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
}
