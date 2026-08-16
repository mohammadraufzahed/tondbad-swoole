<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Relations;

use Closure;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\ModelBuilder;

class MorphTo extends Relation
{
    /** @var array<string, class-string<Model>> */
    protected static array $morphMap = [];

    protected string $typeColumn;

    protected string $idColumn;

    public function __construct(
        Model $parent,
        string $typeColumn,
        string $idColumn,
        string $relationName,
    ) {
        $this->parent = $parent;
        $this->typeColumn = $typeColumn;
        $this->idColumn = $idColumn;
        $this->relationName = $relationName;
        $this->foreignKey = $idColumn;
        $this->localKey = '';
        $this->related = '';

        $this->query = $this->newQuery();
    }

    /** @param array<string, class-string<Model>> $map */
    public static function morphMap(array $map, bool $merge = false): void
    {
        self::$morphMap = $merge ? array_merge(self::$morphMap, $map) : $map;
    }

    /** @return array<string, class-string<Model>> */
    public static function getMorphMap(): array
    {
        return self::$morphMap;
    }

    public static function getActualClassName(string $type): ?string
    {
        return self::$morphMap[$type] ?? null;
    }

    public function getResults(): mixed
    {
        $type = $this->parent->getAttribute($this->typeColumn);

        if ($type === null || $type === '') {
            return null;
        }

        $class = self::getActualClassName((string) $type);

        if ($class === null) {
            return null;
        }

        return (new $class())
            ->newQuery()
            ->where((new $class())->getKeyName(), '=', $this->parent->getAttribute($this->idColumn))
            ->first();
    }

    public function getEager(): array
    {
        return [];
    }

    public function addConstraints(): void
    {
    }

    public function addEagerConstraints(array $models): void
    {
    }

    public function match(array $models, array $results): void
    {
        foreach ($models as $model) {
            $model->setRelation($this->relationName, null);
        }
    }

    public function addHasExistenceQuery(
        ModelBuilder $parent,
        string $parentTable,
        string $operator,
        int $count,
        string $boolean,
        bool $not,
        ?Closure $callback = null,
    ): bool {
        $morphMap = self::getMorphMap();

        if ($morphMap === []) {
            throw new \LogicException('morphTo has()/whereHas() requires a morphMap to be registered.');
        }

        if ($count < 1 && $operator === '<=') {
            // `<= 0` is equivalent to `doesntHave` and is handled by the caller.
        }

        if ($count > 1 && ($operator === '>=' || $operator === '>' || $operator === '=')) {
            if ($not) {
                return true;
            }

            if ($boolean === 'or') {
                $parent->orWhereRaw('0 = 1');
            } else {
                $parent->whereRaw('0 = 1');
            }

            return true;
        }

        $parent->where(function (ModelBuilder $group) use ($parentTable, $morphMap, $callback, $not): void {
            $first = true;

            foreach ($morphMap as $type => $class) {
                /** @var Model $instance */
                $instance = new $class();
                $relatedTable = $instance->getTable();
                $relatedKey = $instance->getKeyName();

                $sub = $instance->newQuery()
                    ->from($relatedTable)
                    ->whereColumn($relatedTable . '.' . $relatedKey, '=', $parentTable . '.' . $this->idColumn)
                    ->where($parentTable . '.' . $this->typeColumn, '=', $type);

                if ($callback !== null) {
                    $callback($sub);
                }

                $innerBoolean = $not ? 'and' : ($first ? 'and' : 'or');
                $group->whereExists($sub, $innerBoolean, $not);
                $first = false;
            }
        }, null, null, $boolean);

        return true;
    }

    public function getRelationExistenceQuery(ModelBuilder $parent, string $parentTable): ModelBuilder
    {
        throw new \LogicException('morphTo existence queries are handled by addHasExistenceQuery.');
    }

    public function getHasGroupByColumn(): string
    {
        return $this->parent->getTable() . '.' . $this->idColumn;
    }

    protected function newQuery(): ModelBuilder
    {
        /** @var ModelBuilder $query */
        $query = $this->parent->newQuery();

        return $query;
    }
}
