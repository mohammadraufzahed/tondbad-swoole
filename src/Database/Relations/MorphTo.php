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

    protected string $morphType;

    /** @var array<string, list<mixed>> */
    protected array $eagerGroups = [];

    protected ?Model $relatedModel = null;

    public function __construct(Model $parent, string $name, ?string $type = null, ?string $id = null, ?string $ownerKey = null)
    {
        $type ??= $name . '_type';
        $id ??= $name . '_id';
        $ownerKey ??= (new MorphPlaceholder())->getKeyName();

        $this->morphType = $type;

        parent::__construct($parent, MorphPlaceholder::class, $id, $ownerKey, $name);
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
        if (isset(self::$morphMap[$type])) {
            return self::$morphMap[$type];
        }

        if ($type !== '' && (is_subclass_of($type, Model::class) || $type === Model::class)) {
            return $type;
        }

        return null;
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

            $this->eagerGroups[(string) $type][] = $key;
        }
    }

    public function getEager(): array
    {
        $results = [];

        foreach ($this->eagerGroups as $type => $keys) {
            $class = self::getActualClassName((string) $type);

            if ($class === null) {
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
            $class = $type !== null ? self::getActualClassName((string) $type) : null;
            $match = null;

            if ($class !== null) {
                foreach ($results as $result) {
                    if ($result instanceof $class && $result->getAttribute($this->localKey) == $key) {
                        $match = $result;

                        break;
                    }
                }
            }

            $model->setRelation($this->relationName, $match);
        }
    }

    public function addWhereHasConstraints(string $parentTable): void
    {
        throw new \LogicException('has()/whereHas()/doesntHave() on morphTo relations are handled by addHasExistenceQuery.');
    }

    public function getWhereHasGroupByColumn(): string
    {
        throw new \LogicException('has()/whereHas()/doesntHave() on morphTo relations are handled by addHasExistenceQuery.');
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
            throw new \LogicException('morphTo has()/whereHas()/doesntHave() requires a morphMap to be registered.');
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
                    ->whereColumn($relatedTable . '.' . $relatedKey, '=', $parentTable . '.' . $this->foreignKey)
                    ->where($parentTable . '.' . $this->morphType, '=', $type);

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

    protected function relatedModel(): ?Model
    {
        if ($this->relatedModel !== null) {
            return $this->relatedModel;
        }

        $type = $this->parent->getAttribute($this->morphType);

        if ($type === null) {
            return null;
        }

        $class = self::getActualClassName((string) $type);

        if ($class === null) {
            return null;
        }

        $this->relatedModel = new $class();

        return $this->relatedModel;
    }
}
