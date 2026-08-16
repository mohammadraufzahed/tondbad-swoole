<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\ModelBuilder;
use TondbadSwoole\Database\Query\Builder;

class BelongsToMany extends Relation
{
    /**
     * @var list<string>
     */
    protected array $pivotColumns = [];

    protected bool $withTimestamps = false;

    /**
     * @var class-string<Model>|null
     */
    protected ?string $pivotModel = null;

    public function __construct(
        Model $parent,
        string $related,
        protected ?string $table = null,
        protected ?string $foreignPivotKey = null,
        protected ?string $relatedPivotKey = null,
        protected ?string $parentKey = null,
        protected ?string $relatedKey = null,
        ?string $relationName = null,
    ) {
        $relationName ??= 'relation';

        $instance = new $related();

        $this->table ??= $this->resolvePivotTableName($parent, $instance);
        $this->foreignPivotKey ??= $this->resolveForeignPivotKey($parent);
        $this->relatedPivotKey ??= $this->resolveRelatedPivotKey($instance);
        $this->parentKey ??= $parent->getKeyName();
        $this->relatedKey ??= $instance->getKeyName();

        parent::__construct($parent, $related, $this->relatedPivotKey, $this->relatedKey, $relationName);
    }

    public function getResults(): array
    {
        $this->addConstraints();

        $results = $this->query->get();

        $this->hydratePivotRelation($results, [$this->parent->getAttribute($this->parentKey)]);

        return $results;
    }

    public function getEager(): array
    {
        return $this->query->get();
    }

    public function addConstraints(): void
    {
        $this->addPivotConstraints($this->query, [$this->parent->getAttribute($this->parentKey)]);
    }

    public function addEagerConstraints(array $models): void
    {
        $this->addPivotConstraints($this->query, $this->getKeys($models, (string) $this->parentKey));
    }

    public function match(array $models, array $results): void
    {
        $pivotRows = $this->getPivotRowsForModels($models);
        $dictionary = [];

        foreach ($pivotRows as $pivotRow) {
            $parentId = $pivotRow[$this->foreignPivotKey];
            $relatedId = $pivotRow[$this->relatedPivotKey];

            $dictionary[$parentId][] = $relatedId;
        }

        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[$result->getAttribute($this->relatedKey)] = $result;
        }

        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            $relatedIds = $dictionary[$parentId] ?? [];
            $matches = [];

            foreach ($relatedIds as $relatedId) {
                if (isset($resultMap[$relatedId])) {
                    $matches[] = $resultMap[$relatedId];
                }
            }

            $model->setRelation($this->relationName, $matches);
        }
    }

    public function addWhereHasConstraints(string $parentTable): void
    {
        $this->query->whereExists(function (Builder $query) use ($parentTable) {
            return $query->from($this->table)
                ->whereColumn($this->table . '.' . $this->relatedPivotKey, '=', $this->getRelatedTable() . '.' . $this->relatedKey)
                ->whereColumn($this->table . '.' . $this->foreignPivotKey, '=', $parentTable . '.' . $this->parentKey);
        });
    }

    public function getRelationExistenceQueryForParent(ModelBuilder $parent, string $parentTable): Builder
    {
        return $parent->toBase()
            ->from($this->table)
            ->select($parent->raw('1'))
            ->whereColumn($this->table . '.' . $this->foreignPivotKey, '=', $parentTable . '.' . $this->parentKey);
    }

    public function getWhereHasGroupByColumn(): string
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }

    /**
     * @param array<int|string|Model> $ids
     * @param array<string, mixed> $pivotAttributes
     */
    public function attach(array|int|string $ids, array $pivotAttributes = []): void
    {
        $ids = $this->parseIds($ids);

        if ($ids === []) {
            return;
        }

        $records = [];

        foreach ($ids as $id) {
            $records[] = array_merge(
                [
                    $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                    $this->relatedPivotKey => $id,
                ],
                $this->hasPivotColumn($this->createdAt()) ? [$this->createdAt() => $this->parent->freshTimestampString()] : [],
                $this->hasPivotColumn($this->updatedAt()) ? [$this->updatedAt() => $this->parent->freshTimestampString()] : [],
                $pivotAttributes,
            );
        }

        foreach ($records as $record) {
            $this->newPivotQuery()->insert($record);
        }
    }

    /**
     * @param array<int|string>|int|string|null $ids
     */
    public function detach(array|int|string|null $ids = null): int
    {
        $query = $this->newPivotQuery();

        if ($ids !== null) {
            $query->whereIn($this->relatedPivotKey, $this->parseIds($ids));
        }

        return $query->delete();
    }

    /**
     * @param array<int|string|Model> $ids
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $ids = $this->parseIds($ids);

        $current = array_map(
            fn ($row) => $row[$this->relatedPivotKey],
            $this->newPivotQuery()->select([$this->relatedPivotKey])->get(),
        );

        $records = array_map(fn ($id) => (string) $id, $ids);
        $current = array_map(fn ($id) => (string) $id, $current);

        $attach = array_diff($records, $current);
        $detach = $detaching ? array_diff($current, $records) : [];

        if ($attach !== []) {
            $this->attach($attach);
        }

        if ($detach !== []) {
            $this->detach($detach);
        }

        return [
            'attached' => array_values($attach),
            'detached' => array_values($detach),
            'updated' => [],
        ];
    }

    /**
     * @param array<int|string|Model> $ids
     */
    public function toggle(array $ids): array
    {
        $ids = $this->parseIds($ids);

        $current = array_map(
            fn ($row) => $row[$this->relatedPivotKey],
            $this->newPivotQuery()->select([$this->relatedPivotKey])->get(),
        );

        $current = array_map(fn ($id) => (string) $id, $current);
        $ids = array_map(fn ($id) => (string) $id, $ids);

        $attach = array_diff($ids, $current);
        $detach = array_intersect($ids, $current);

        if ($attach !== []) {
            $this->attach($attach);
        }

        if ($detach !== []) {
            $this->detach($detach);
        }

        return [
            'attached' => array_values($attach),
            'detached' => array_values($detach),
        ];
    }

    public function withPivot(array|string $columns): self
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            is_array($columns) ? $columns : [$columns]
        );

        return $this;
    }

    public function withTimestamps(): self
    {
        $this->withTimestamps = true;

        return $this;
    }

    public function using(string $class): self
    {
        $this->pivotModel = $class;

        return $this;
    }

    /**
     * @param array<Model> $models
     * @return array<array<string, mixed>>
     */
    protected function getPivotRowsForModels(array $models): array
    {
        $keys = $this->getKeys($models, (string) $this->parentKey);

        if ($keys === []) {
            return [];
        }

        return $this->newPivotQuery()
            ->select($this->getPivotColumns())
            ->whereIn($this->foreignPivotKey, $keys)
            ->get();
    }

    protected function newPivotQuery(): Builder
    {
        return $this->parent->getConnection()->table($this->table);
    }

    protected function newPivotInstance(): Model
    {
        $class = $this->pivotModel ?? Pivot::class;
        $pivot = new $class();

        if ($pivot instanceof Pivot || $pivot instanceof Model) {
            $pivot->setTable($this->table);
        }

        return $pivot;
    }

    /**
     * @param array<Model> $models
     */
    protected function hydratePivotRelation(array $models, array $parentIds): void
    {
        $pivotRows = $this->getPivotRowsForModels([$this->parent]);

        foreach ($pivotRows as $pivotRow) {
            foreach ($models as $model) {
                if ((string) $model->getAttribute($this->relatedKey) !== (string) $pivotRow[$this->relatedPivotKey]) {
                    continue;
                }

                $pivotAttributes = $pivotRow;
                unset($pivotAttributes[$this->foreignPivotKey], $pivotAttributes[$this->relatedPivotKey]);

                $model->setRelation('pivot', $this->newPivot((array) $pivotAttributes, true));
            }
        }
    }

    protected function newPivot(array $attributes, bool $exists): Model
    {
        $pivot = $this->newPivotInstance();
        $pivot->setRawAttributes($attributes, true);
        $pivot->exists = $exists;

        return $pivot;
    }

    /**
     * @param array<int|string|Model>|int|string $ids
     * @return list<int|string>
     */
    protected function parseIds(array|int|string $ids): array
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(
            array_map(
                fn ($id) => $id instanceof Model ? $id->getKey() : $id,
                $ids,
            )
        );
    }

    protected function addPivotConstraints(Builder $query, array $parentIds): void
    {
        if ($parentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function (Builder $query) use ($parentIds) {
            return $query->from($this->table)
                ->whereColumn($this->table . '.' . $this->relatedPivotKey, '=', $this->getRelatedTable() . '.' . $this->relatedKey)
                ->whereIn($this->table . '.' . $this->foreignPivotKey, $parentIds);
        });
    }

    protected function getPivotColumns(): array
    {
        $columns = [$this->foreignPivotKey, $this->relatedPivotKey];

        if ($this->withTimestamps) {
            $columns[] = $this->createdAt();
            $columns[] = $this->updatedAt();
        }

        return array_merge($columns, $this->pivotColumns);
    }

    protected function hasPivotColumn(string $column): bool
    {
        return in_array($column, $this->getPivotColumns(), true);
    }

    protected function createdAt(): string
    {
        return 'created_at';
    }

    protected function updatedAt(): string
    {
        return 'updated_at';
    }

    protected function getRelatedTable(): string
    {
        return $this->newRelatedInstance()->getTable();
    }

    protected function resolvePivotTableName(Model $parent, Model $related): string
    {
        $parts = [
            $parent->snake($parent->getClassBasename()),
            $parent->snake($related->getClassBasename()),
        ];
        sort($parts);

        return implode('_', $parts);
    }

    protected function resolveForeignPivotKey(Model $parent): string
    {
        return $parent->snake($parent->getClassBasename()) . '_' . (is_array($this->parentKey) ? implode('_', $this->parentKey) : $this->parentKey);
    }

    protected function resolveRelatedPivotKey(Model $related): string
    {
        return $related->snake($related->getClassBasename()) . '_' . (is_array($this->relatedKey) ? implode('_', $this->relatedKey) : $this->relatedKey);
    }
}
