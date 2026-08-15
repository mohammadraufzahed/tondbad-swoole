<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use TondbadSwoole\Database\Criteria\Criteria;
use TondbadSwoole\Database\Pagination\LengthAwarePaginator;
use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Database\Query\Expression;
use TondbadSwoole\Database\Relations\Relation;
use TondbadSwoole\Database\Scopes\Scope;
use TondbadSwoole\Database\Scopes\SoftDeleteScope;

class ModelBuilder extends Builder
{
    protected ?string $model = null;

    /** @var array<string, ?Closure> */
    protected array $eagerLoad = [];

    protected ?EntityManagerInterface $entityManager = null;

    /** @var list<Scope> */
    protected array $globalScopes = [];

    /** @var list<class-string<Scope>> */
    protected array $removedGlobalScopes = [];

    protected bool $scopesApplied = false;

    protected ?int $cacheSeconds = null;

    protected ?string $cacheKey = null;

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setEntityManager(?EntityManagerInterface $entityManager): self
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    public function getEntityManager(): ?EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function with(array|string $relations): self
    {
        $args = is_array($relations) ? $relations : func_get_args();

        if (!is_array($relations) && count($args) === 2 && is_string($args[0]) && $args[1] instanceof Closure) {
            $this->eagerLoad[$args[0]] = $args[1];

            return $this;
        }

        foreach ($args as $key => $value) {
            if ($value instanceof Closure) {
                $this->eagerLoad[$key] = $value;
            } elseif (is_string($value) && $value !== '') {
                $this->eagerLoad[$value] = null;
            }
        }

        return $this;
    }

    public function newQuery(): self
    {
        return (new static($this->connection, $this->grammar))
            ->setModel($this->model ?? static::class)
            ->from($this->from)
            ->setEntityManager($this->entityManager);
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
        $this->applyScopes();

        $key = $this->getCacheKey();
        $cache = $this->cacheSeconds !== null ? cache() : null;

        if ($cache !== null && $key !== null) {
            $rows = $cache->get($key);

            if ($rows !== null) {
                return $this->hydrateFromRows($rows);
            }
        }

        $rows = $this->toBase()->get();

        if ($cache !== null && $key !== null) {
            $cache->set($key, $rows, $this->cacheSeconds);
        }

        return $this->hydrateFromRows($rows);
    }

    public function remember(int $seconds, ?string $key = null): self
    {
        $this->cacheSeconds = $seconds;
        $this->cacheKey = $key;

        return $this;
    }

    public function flushCache(?string $key = null): self
    {
        $key ??= $this->getCacheKey();

        if ($key !== null) {
            cache()?->delete($key);
        }

        return $this;
    }

    protected function getCacheKey(): ?string
    {
        if ($this->cacheKey !== null) {
            return $this->cacheKey;
        }

        if ($this->cacheSeconds === null) {
            return null;
        }

        $base = $this->toBase();

        return 'orm.query.' . md5($base->toSql() . serialize($base->getBindings()));
    }

    protected function hydrateFromRows(array $rows): array
    {
        if ($this->model === null) {
            return $rows;
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrateModelFromRow($row);
        }

        return $this->eagerLoadRelations($models);
    }

    public function first(): mixed
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    public function find(mixed $id, array|string $columns = ['*']): mixed
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        if ($this->model === null) {
            return $this->toBase()->where('id', '=', $id)->first();
        }

        $instance = new $this->model();

        return $this->where($instance->getKeyName(), '=', $id)->first();
    }

    public function findMany(array $ids, array|string $columns = ['*']): array
    {
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        if ($this->model === null) {
            return $this->toBase()->whereIn('id', $ids)->get();
        }

        $instance = new $this->model();

        return $this->whereIn($instance->getKeyName(), $ids)->get();
    }

    public function firstOrNew(array $attributes, array $values = []): Model
    {
        $instance = $this->where($attributes)->first();

        if ($instance !== null) {
            return $instance;
        }

        if ($this->model === null) {
            throw new RuntimeException('Cannot create a model without a model class.');
        }

        $class = $this->model;

        return new $class($attributes + $values);
    }

    public function firstOrFail(): Model
    {
        $model = $this->first();

        if ($model === null) {
            throw new RuntimeException('Model not found.');
        }

        return $model;
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
        $this->applyScopes();
        $this->columns = [new Expression("{$function}({$this->wrapColumn($column)}) as aggregate")];

        $row = $this->toBase()->first();

        return $row !== null ? ($row['aggregate'] ?? null) : null;
    }

    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = $page ?? 1;

        $this->applyScopes();
        $count = (int) $this->toBase()->count();
        $items = $this->forPage($page, $perPage)->get();

        return new LengthAwarePaginator($items, $count, $perPage, $page, $pageName);
    }

    public function whereHas(string $relation, ?Closure $callback = null): self
    {
        return $this->has($relation, '>=', 1, 'and', $callback);
    }

    public function orWhereHas(string $relation, ?Closure $callback = null): self
    {
        return $this->has($relation, '>=', 1, 'or', $callback);
    }

    public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?Closure $callback = null): self
    {
        $relationObj = $this->getRelationObject($relation);

        if ($operator !== '>=' || $count !== 1) {
            $sub = $relationObj->getRelationExistenceQueryForParent($this, $this->from)
                ?? $this->getRelationHasQuery($relationObj);
        } else {
            $sub = $this->getRelationHasQuery($relationObj);
        }

        if ($callback !== null) {
            $callback($sub);
        }

        if ($operator !== '>=' || $count !== 1) {
            $sub->select($this->raw('1'))
                ->groupBy($relationObj->getWhereHasGroupByColumn())
                ->having($this->raw('count(*)'), $operator, $count);
        }

        return $this->whereExists($sub, $boolean, false);
    }

    public function orHas(string $relation, string $operator = '>=', int $count = 1): self
    {
        return $this->has($relation, $operator, $count, 'or');
    }

    public function doesntHave(string $relation, string $boolean = 'and', ?Closure $callback = null): self
    {
        $relationObj = $this->getRelationObject($relation);
        $sub = $this->getRelationHasQuery($relationObj);

        if ($callback !== null) {
            $callback($sub);
        }

        return $this->whereNotExists($sub, $boolean);
    }

    public function orDoesntHave(string $relation): self
    {
        return $this->doesntHave($relation, 'or');
    }

    public function whereRelation(string $relation, string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->whereHas($relation, fn ($query) => $query->where($column, $operator, $value));
    }

    public function orWhereRelation(string $relation, string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->orWhereHas($relation, fn ($query) => $query->where($column, $operator, $value));
    }

    public function withCount(array|string $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($relations as $key => $value) {
            $name = is_string($key) ? $key : $value;
            $callback = $value instanceof Closure ? $value : null;
            $relation = is_string($key) ? $key : $value;

            $this->addSelect([$name . '_count' => $this->getRelationCountQuery($relation, $callback)]);
        }

        return $this;
    }

    public function withSum(array|string $relations, string $column): self
    {
        return $this->withAggregate($relations, $column, 'sum');
    }

    public function withAvg(array|string $relations, string $column): self
    {
        return $this->withAggregate($relations, $column, 'avg');
    }

    public function withMax(array|string $relations, string $column): self
    {
        return $this->withAggregate($relations, $column, 'max');
    }

    public function withMin(array|string $relations, string $column): self
    {
        return $this->withAggregate($relations, $column, 'min');
    }

    public function loadMissing(Model $model, array|string $relations): Model
    {
        $relations = is_array($relations) ? $relations : [$relations];

        foreach ($relations as $relation) {
            if (!$this->relationLoaded($model, $relation)) {
                $model->load($relation);
            }
        }

        return $model;
    }

    public function loadCount(Model $model, array|string $relations): Model
    {
        $relations = is_array($relations) ? $relations : [$relations];

        foreach ($relations as $relation) {
            $model->setRelation($relation . '_count', $this->getRelationCount($model, $relation));
        }

        return $model;
    }

    public function loadAggregate(Model $model, string $relation, string $column, string $function): Model
    {
        $model->setRelation($relation . '_' . strtolower($function) . '_' . $column, $this->getRelationAggregate($model, $relation, $column, $function));

        return $model;
    }

    public function loadRelations(array $models, array|string $relations): array
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        return $this->newQuery()
            ->with($relations)
            ->eagerLoadRelations($models);
    }

    public function cursor(int $chunkSize = 1000): \Generator
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $chunkSize)->get();
            $count = count($results);

            foreach ($results as $result) {
                yield $result;
            }

            $page++;
        } while ($count === $chunkSize);
    }

    public function chunkById(int $count, Closure $callback, string $column = 'id'): bool
    {
        $lastId = null;

        do {
            $query = $this->newQuery()->limit($count);

            if ($lastId !== null) {
                $query->where($column, '>', $lastId);
            }

            $results = $query->get();

            if ($results === []) {
                break;
            }

            $lastResult = $results[count($results) - 1];
            $lastId = $lastResult instanceof Model ? $lastResult->getAttribute($column) : ($lastResult[$column] ?? null);

            if ($callback($results) === false) {
                return false;
            }
        } while (count($results) === $count);

        return true;
    }

    public function applyCriteria(Criteria $criteria): self
    {
        foreach ($criteria->getWheres() as $where) {
            $field = $where['field'];
            $operator = strtolower($where['operator']);
            $value = $where['value'];
            $boolean = $where['boolean'];

            if ($field instanceof Expression) {
                $this->whereRaw((string) $field, $value === null ? [] : [$value], $boolean);

                continue;
            }

            match ($operator) {
                'in' => $this->whereIn($field, is_array($value) ? $value : [$value], $boolean),
                'not in' => $this->whereNotIn($field, is_array($value) ? $value : [$value], $boolean),
                'is null' => $this->whereNull($field, $boolean),
                'is not null' => $this->whereNotNull($field, $boolean),
                'between' => $this->whereBetween($field, is_array($value) ? $value : [$value], $boolean),
                default => $this->where($field, $operator, $value, $boolean),
            };
        }

        foreach ($criteria->getOrderings() as $field => $direction) {
            $this->orderBy($field, $direction);
        }

        if ($criteria->getFirstResult() !== null) {
            $this->offset($criteria->getFirstResult());
        }

        if ($criteria->getMaxResults() !== null) {
            $this->limit($criteria->getMaxResults());
        }

        return $this;
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
     * @param array<string, mixed> $row
     */
    private function hydrateModelFromRow(array $row): Model
    {
        if ($this->model === null) {
            throw new \RuntimeException('Cannot hydrate a model without a model class.');
        }

        $class = $this->model;
        $key = (new $class())->getKeyFromRow($row);

        if ($this->entityManager !== null && $key !== null && $key !== []) {
            $managed = $this->entityManager->getManaged($class, $key);

            if ($managed instanceof $class) {
                $this->applyExtraSelectColumns($managed, $row);

                return $managed;
            }
        }

        $model = $class::newFromBuilder($row);

        if ($this->entityManager !== null) {
            $this->entityManager->getUnitOfWork()->persist($model);
        }

        $this->applyExtraSelectColumns($model, $row);

        return $model;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyExtraSelectColumns(Model $model, array $row): void
    {
        foreach ($this->columns as $alias => $column) {
            if (is_string($alias) && array_key_exists($alias, $row)) {
                $model->setAttribute($alias, $row[$alias]);
            }
        }
    }

    /**
     * @param list<Model> $models
     * @param array<string, ?Closure> $relations
     */
    private function eagerLoadRelationsFor(array $models, array $relations): void
    {
        if ($models === [] || $relations === []) {
            return;
        }

        $groups = [];

        foreach ($relations as $relation => $constraints) {
            if (is_int($relation)) {
                $relation = $constraints;
                $constraints = null;
            }

            $segments = explode('.', $relation, 2);
            $first = $segments[0];
            $rest = $segments[1] ?? null;

            if (!isset($groups[$first])) {
                $groups[$first] = ['constraints' => null, 'nested' => []];
            }

            if ($rest === null) {
                $groups[$first]['constraints'] = $constraints;
            } else {
                $groups[$first]['nested'][$rest] = $constraints;
            }
        }

        foreach ($groups as $relation => $group) {
            $this->eagerLoadRelation($models, $relation, $group['constraints']);

            $related = $this->relatedModels($models, $relation);

            if ($related === []) {
                continue;
            }

            if ($group['nested'] === []) {
                continue;
            }

            $relatedModel = $related[0]::class;

            (new static($this->connection, $this->grammar))
                ->setModel($relatedModel)
                ->from((new $relatedModel())->getTable())
                ->setEntityManager($this->entityManager)
                ->eagerLoadRelationsFor($related, $group['nested']);
        }
    }

    protected function eagerLoadRelation(array $models, string $relation, ?Closure $constraints = null): void
    {
        $relationObj = $this->getRelationObject($relation);

        if ($constraints !== null) {
            $constraints($relationObj->getQuery());
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

    protected function getRelationObject(string $relation): Relation
    {
        if ($this->model === null) {
            throw new RuntimeException('Cannot load relations without a model class.');
        }

        $instance = new $this->model();

        if (!method_exists($instance, $relation)) {
            throw new RuntimeException("Relation [{$relation}] not found on model [{$this->model}].");
        }

        $relationObj = $instance->$relation();

        if (!$relationObj instanceof Relation) {
            throw new RuntimeException("[{$relation}] is not a relation on model [{$this->model}].");
        }

        return $relationObj;
    }

    protected function getRelationHasQuery(Relation $relationObj): Builder
    {
        $query = $relationObj->getQuery();
        $query->from($relationObj->getRelated()->getTable());
        $relationObj->addWhereHasConstraints($this->from);

        return $query;
    }

    protected function getRelationCountQuery(string $relation, ?Closure $callback = null): Builder
    {
        $relationObj = $this->getRelationObject($relation);
        $query = $this->getRelationHasQuery($relationObj);

        if ($callback !== null) {
            $callback($query);
        }

        $query->select($this->raw('count(*)'));

        return $query;
    }

    protected function getRelationAggregateQuery(string $relation, string $column, string $function, ?Closure $callback = null): Builder
    {
        $relationObj = $this->getRelationObject($relation);
        $query = $this->getRelationHasQuery($relationObj);

        if ($callback !== null) {
            $callback($query);
        }

        $query->select($this->raw("{$function}({$this->grammar->wrap($column)})"));

        return $query;
    }

    protected function withAggregate(array|string $relations, string $column, string $function): self
    {
        $relations = is_array($relations) ? $relations : [$relations];

        foreach ($relations as $key => $value) {
            $name = is_string($key) ? $key : $value;
            $callback = $value instanceof Closure ? $value : null;
            $relation = is_string($key) ? $key : $value;

            $alias = $name . '_' . strtolower($function) . '_' . $column;

            $this->addSelect([$alias => $this->getRelationAggregateQuery($relation, $column, $function, $callback)]);
        }

        return $this;
    }

    protected function getRelationCount(Model $model, string $relation): int
    {
        $relationObj = $this->getRelationObjectForModel($model, $relation);
        $relationObj->addConstraints();

        return (int) $relationObj->getQuery()->count();
    }

    protected function getRelationAggregate(Model $model, string $relation, string $column, string $function): mixed
    {
        $relationObj = $this->getRelationObjectForModel($model, $relation);
        $relationObj->addConstraints();

        return $relationObj->getQuery()->aggregate($function, $column);
    }

    protected function getRelationObjectForModel(Model $model, string $relation): Relation
    {
        if (!method_exists($model, $relation)) {
            throw new RuntimeException("Relation [{$relation}] not found on model [" . $model::class . '].');
        }

        $relationObj = $model->$relation();

        if (!$relationObj instanceof Relation) {
            throw new RuntimeException("[{$relation}] is not a relation on model [" . $model::class . '].');
        }

        return $relationObj;
    }

    protected function relationLoaded(Model $model, string $relation): bool
    {
        return array_key_exists($relation, $model->getRelations());
    }

    public function withGlobalScope(Scope $scope): self
    {
        $this->globalScopes[] = $scope;

        return $this;
    }

    public function withoutGlobalScope(string $scopeClass): self
    {
        $this->removedGlobalScopes[] = $scopeClass;

        return $this;
    }

    public function withoutGlobalScopes(): self
    {
        $this->removedGlobalScopes = array_map(
            fn (Scope $scope) => $scope::class,
            $this->globalScopes
        );

        return $this;
    }

    public function withTrashed(): self
    {
        return $this->withoutGlobalScope(SoftDeleteScope::class);
    }

    public function withoutTrashed(): self
    {
        return $this;
    }

    public function onlyTrashed(): self
    {
        return $this->withoutGlobalScope(SoftDeleteScope::class)
            ->whereNotNull($this->getModelTable() . '.deleted_at');
    }

    protected function applyScopes(): self
    {
        if ($this->scopesApplied) {
            return $this;
        }

        $this->scopesApplied = true;

        foreach ($this->globalScopes as $scope) {
            if (in_array($scope::class, $this->removedGlobalScopes, true)) {
                continue;
            }

            if ($this->model !== null) {
                $scope->apply($this, new $this->model());
            }
        }

        return $this;
    }

    protected function getModelTable(): string
    {
        if ($this->model === null) {
            return $this->from;
        }

        return (new $this->model())->getTable();
    }
}
