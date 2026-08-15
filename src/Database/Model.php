<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TondbadSwoole\Database\Attributes\Cascade;
use TondbadSwoole\Database\Attributes\Embedded;
use TondbadSwoole\Database\Attributes\Version;
use TondbadSwoole\Database\Casts\CastsAttributes;
use TondbadSwoole\Database\Relations\BelongsTo;
use TondbadSwoole\Database\Relations\BelongsToMany;
use TondbadSwoole\Database\Relations\HasMany;
use TondbadSwoole\Database\Relations\HasOne;
use TondbadSwoole\Database\Relations\MorphMany;
use TondbadSwoole\Database\Relations\MorphOne;
use TondbadSwoole\Database\Relations\MorphTo;
use TondbadSwoole\Database\Relations\Relation;
use TondbadSwoole\Database\Scopes\Scope;
use UnitEnum;

abstract class Model
{
    protected ?string $table = null;

    protected string|array $primaryKey = 'id';

    protected bool $incrementing = true;

    protected string|array $keyType = 'int';

    protected bool $timestamps = true;

    protected string $dateFormat = 'Y-m-d H:i:s';

    protected ?string $connection = null;

    protected array $fillable = [];

    protected array $guarded = ['*'];

    protected array $hidden = [];

    protected array $casts = [];

    protected array $attributes = [];

    protected array $original = [];

    protected array $relations = [];

    protected array $embeddedObjects = [];

    private static array $embeddableMap = [];

    private static array $versionPropertyMap = [];

    private static array $cascadeMap = [];

    private static array $booted = [];

    /** @var array<class-string, list<Scope>> */
    private static array $globalScopes = [];

    public bool $exists = false;

    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->exists = $exists;
        if ($exists) {
            $this->setRawAttributes($attributes);
        } else {
            $this->fill($attributes);
        }
        $this->syncOriginal();
        $this->bootIfNotBooted();
    }

    public static function query(): ModelBuilder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): ModelBuilder
    {
        $connection = $this->getConnection();

        $builder = (new ModelBuilder($connection, $connection->getGrammar()))
            ->from($this->getTable())
            ->setModel(static::class);

        $entityManager = $this->getEntityManager();
        if ($entityManager !== null) {
            $builder->setEntityManager($entityManager);
        }

        foreach (static::getGlobalScopes() as $scope) {
            $builder->withGlobalScope($scope);
        }

        return $builder;
    }

    public function getEntityManager(): ?EntityManagerInterface
    {
        if (!function_exists('em')) {
            return null;
        }

        return em();
    }

    public static function newFromBuilder(array $attributes = []): static
    {
        return new static($attributes, true);
    }

    public static function all(array|string $columns = ['*']): array
    {
        return static::query()->select($columns)->get();
    }

    public static function find(mixed $id, array|string $columns = ['*']): ?static
    {
        return static::query()->find($id, $columns);
    }

    public static function findOrFail(mixed $id, array|string $columns = ['*']): static
    {
        $model = static::find($id, $columns);

        if ($model === null) {
            throw new RuntimeException('Model not found.');
        }

        return $model;
    }

    public static function firstWhere(array $conditions, array|string $columns = ['*']): ?static
    {
        $query = static::query()->select($columns);
        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        return $query->first();
    }

    public static function findMany(array $ids, array|string $columns = ['*']): array
    {
        return static::query()->findMany($ids, $columns);
    }

    public static function firstOrNew(array $attributes, array $values = []): static
    {
        return static::query()->firstOrNew($attributes, $values);
    }

    public static function firstOrFail(): static
    {
        return static::query()->firstOrFail();
    }

    public static function destroy(mixed $id): int
    {
        if ($id instanceof Model) {
            return $id->delete();
        }

        if (is_array($id)) {
            $count = 0;
            foreach ($id as $single) {
                $count += static::destroy($single);
            }

            return $count;
        }

        $model = static::find($id);

        return $model !== null ? $model->delete() : 0;
    }

    public static function count(string $column = '*'): int
    {
        return static::query()->count($column);
    }

    public static function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page')
    {
        return static::query()->paginate($perPage, $page, $pageName);
    }

    public static function cursor(int $chunkSize = 1000): \Generator
    {
        return static::query()->cursor($chunkSize);
    }

    public static function chunkById(int $count, Closure $callback, string $column = 'id'): bool
    {
        return static::query()->chunkById($count, $callback, $column);
    }

    public static function withTrashed(): ModelBuilder
    {
        return static::query()->withTrashed();
    }

    public static function onlyTrashed(): ModelBuilder
    {
        return static::query()->onlyTrashed();
    }

    public static function with(array|string $relations): ModelBuilder
    {
        return static::query()->with($relations);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $instance = static::firstWhere($attributes);

        if ($instance !== null) {
            return $instance;
        }

        return static::create($attributes + $values);
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $instance = static::firstWhere($attributes);

        if ($instance !== null) {
            $instance->fill($values);
            $instance->save();

            return $instance;
        }

        return static::create($attributes + $values);
    }

    public function getTable(): string
    {
        return $this->table ?? $this->pluralize($this->getClassBasename());
    }

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getKey(): mixed
    {
        if (is_string($this->primaryKey)) {
            return $this->getAttribute($this->primaryKey);
        }

        $key = [];
        foreach ($this->primaryKey as $part) {
            $key[$part] = $this->getAttribute($part);
        }

        return $key;
    }

    public function getKeyName(): string|array
    {
        return $this->primaryKey;
    }

    public function getKeyFromRow(array $row): mixed
    {
        if (is_string($this->primaryKey)) {
            return $row[$this->primaryKey] ?? null;
        }

        $key = [];
        foreach ($this->primaryKey as $part) {
            $key[$part] = $row[$part] ?? null;
        }

        return $key;
    }

    public function getQualifiedKeyName(): string
    {
        $key = $this->getKeyName();

        return $this->getTable() . '.' . (is_array($key) ? implode('.', $key) : $key);
    }

    public function getForeignKey(): string
    {
        return $this->snake($this->getClassBasename()) . '_' . implode('_', (array) $this->primaryKey);
    }

    public function getConnection(): ConnectionInterface
    {
        $app = app();

        if ($app === null || !property_exists($app, 'container')) {
            throw new RuntimeException('No application container available.');
        }

        return $app->container->make(ConnectionInterface::class);
    }

    public function getGrammar(): Query\Grammar
    {
        return $this->getConnection()->getGrammar();
    }

    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    public function isIncrementing(): bool
    {
        return $this->incrementing && is_string($this->primaryKey);
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable((string) $key)) {
                $this->setAttribute((string) $key, $value);
            }
        }

        return $this;
    }

    public function forceFill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }

        return $this;
    }

    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = [];
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }
        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if ($this->isEmbedded($key)) {
            return $this->getEmbedded($key);
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->castToPHP($key, $this->attributes[$key]);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        if ($this->isEmbedded($key)) {
            $this->setEmbedded($key, $value);

            return $this;
        }

        $this->attributes[$key] = $this->castToPHP($key, $value);

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? $default;
    }

    public function syncOriginal(): self
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key === null) {
            return $this->getDirty() !== [];
        }

        return array_key_exists($key, $this->getDirty());
    }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isFillable(string $key): bool
    {
        if ($this->fillable !== [] && in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->guarded === ['*']) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    public function save(): bool
    {
        $entityManager = $this->getEntityManager();

        if ($entityManager !== null) {
            $entityManager->persist($this)->flush();

            return true;
        }

        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    public function update(array $attributes = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save();
    }

    public function usesSoftDeletes(): bool
    {
        return false;
    }

    protected function softDelete(): int
    {
        return 0;
    }

    public function delete(): int
    {
        if (!$this->exists) {
            return 0;
        }

        if ($this->usesSoftDeletes()) {
            return $this->softDelete();
        }

        $entityManager = $this->getEntityManager();

        if ($entityManager !== null) {
            $entityManager->remove($this)->flush();

            return 1;
        }

        return $this->performDelete();
    }

    public function fresh(): ?static
    {
        if (!$this->exists || $this->getKey() === null) {
            return null;
        }

        return static::find($this->getKey());
    }

    public function refresh(): self
    {
        $fresh = $this->fresh();
        if ($fresh !== null) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->exists = true;
            $this->syncOriginal();
            $this->relations = [];
        }

        return $this;
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->hidden, true)) {
                continue;
            }
            $array[$key] = $this->castToArray($key, $value);
        }

        foreach ($this->relations as $key => $value) {
            if (in_array($key, $this->hidden, true)) {
                continue;
            }
            $array[$key] = $this->castRelationToArray($value);
        }

        return $array;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '';
    }

    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function load(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $fresh = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->with($relations)
            ->first();

        if ($fresh instanceof Model) {
            foreach ($fresh->getRelations() as $name => $value) {
                $this->setRelation($name, $value);
            }
        }

        return $this;
    }

    public function loadMissing(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $missing = [];
        foreach ($relations as $relation) {
            if (!array_key_exists($relation, $this->relations)) {
                $missing[] = $relation;
            }
        }

        if ($missing !== []) {
            $this->load($missing);
        }

        return $this;
    }

    public function loadCount(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $this->newQuery()->loadCount($this, $relations);

        return $this;
    }

    public function loadAggregate(string $relation, string $column, string $function): self
    {
        $this->newQuery()->loadAggregate($this, $relation, $column, $function);

        return $this;
    }

    public function __get(string $key): mixed
    {
        if ($this->isEmbedded($key)) {
            return $this->getEmbedded($key);
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                $relation->setCascade($this->getRelationCascade($key));

                return $this->relations[$key] = $relation->getResults();
            }
        }

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->isEmbedded($key)
            || array_key_exists($key, $this->attributes)
            || array_key_exists($key, $this->relations);
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function setIncrementing(bool $value): self
    {
        $this->incrementing = $value;

        return $this;
    }

    public function getKeyType(): string|array
    {
        return $this->keyType;
    }

    public function getCasts(): array
    {
        $keyName = $this->getKeyName();
        $keyType = $this->getKeyType();

        if (!$this->isIncrementing() && !is_array($keyName)) {
            return $this->casts;
        }

        $keyCasts = [];
        if (is_string($keyName)) {
            $keyCasts[$keyName] = is_string($keyType) ? $keyType : 'int';
        } else {
            foreach ($keyName as $index => $part) {
                $keyCasts[$part] = is_array($keyType) ? ($keyType[$index] ?? 'int') : $keyType;
            }
        }

        return array_merge($this->casts, $keyCasts);
    }

    public function getEmbeddables(): array
    {
        $class = static::class;

        if (isset(self::$embeddableMap[$class])) {
            return self::$embeddableMap[$class];
        }

        $map = [];
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Embedded::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $map[$property->getName()] = [
                'class' => $attribute->class,
                'prefix' => $attribute->prefix,
            ];
        }

        return self::$embeddableMap[$class] = $map;
    }

    public function getVersionProperty(): ?string
    {
        $class = static::class;

        if (array_key_exists($class, self::$versionPropertyMap)) {
            return self::$versionPropertyMap[$class];
        }

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Version::class) !== []) {
                return self::$versionPropertyMap[$class] = $property->getName();
            }
        }

        return self::$versionPropertyMap[$class] = null;
    }

    public function getVersion(): mixed
    {
        $property = $this->getVersionProperty();

        return $property !== null ? $this->getAttribute($property) : null;
    }

    public function setVersion(mixed $value): self
    {
        $property = $this->getVersionProperty();

        if ($property !== null) {
            $this->setAttribute($property, $value);
        }

        return $this;
    }

    private function incrementVersionValue(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value + 1;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value + 1;
        }

        return $value;
    }

    public function getRelationCascade(string $key): array
    {
        $class = static::class;

        if (isset(self::$cascadeMap[$class][$key])) {
            return self::$cascadeMap[$class][$key];
        }

        if (!method_exists($this, $key)) {
            return self::$cascadeMap[$class][$key] = [];
        }

        $reflection = new ReflectionMethod($this, $key);
        $attributes = $reflection->getAttributes(Cascade::class);

        if ($attributes === []) {
            return self::$cascadeMap[$class][$key] = [];
        }

        return self::$cascadeMap[$class][$key] = $attributes[0]->newInstance()->cascade;
    }

    public function isEmbedded(string $key): bool
    {
        return array_key_exists($key, $this->getEmbeddables());
    }

    public function getEmbedded(string $key): ?object
    {
        if (array_key_exists($key, $this->embeddedObjects)) {
            return $this->embeddedObjects[$key];
        }

        if (!$this->isEmbedded($key)) {
            return null;
        }

        $spec = $this->getEmbeddables()[$key];
        $instance = $this->buildEmbedded($spec['class'], $spec['prefix']);

        if ($instance !== null) {
            $this->embeddedObjects[$key] = $instance;
        }

        return $instance;
    }

    public function setEmbedded(string $key, mixed $value): self
    {
        $spec = $this->getEmbeddables()[$key] ?? null;

        if ($spec === null) {
            return $this;
        }

        $class = $spec['class'];
        $prefix = $spec['prefix'];

        if (is_array($value)) {
            $instance = new $class();
            foreach ($value as $property => $propertyValue) {
                $this->setEmbeddedProperty($instance, $property, $propertyValue);
            }
        } elseif (is_object($value) && $value instanceof $class) {
            $instance = $value;
        } else {
            $instance = new $class();
        }

        $this->embeddedObjects[$key] = $instance;

        foreach ($this->getEmbeddedColumns($class, $prefix) as $column => $property) {
            $this->attributes[$column] = $this->getEmbeddedProperty($instance, $property);
        }

        return $this;
    }

    private function buildEmbedded(string $class, string $prefix): ?object
    {
        $instance = new $class();
        $hasValues = false;

        foreach ($this->getEmbeddedColumns($class, $prefix) as $column => $property) {
            if (array_key_exists($column, $this->attributes)) {
                $hasValues = true;
                $this->setEmbeddedProperty($instance, $property, $this->attributes[$column]);
            }
        }

        return $hasValues ? $instance : null;
    }

    private function getEmbeddedColumns(string $class, string $prefix): array
    {
        $columns = [];

        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $columns[$prefix . $property->getName()] = $property->getName();
        }

        return $columns;
    }

    private function getEmbeddedProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionProperty($instance, $property);

        return $reflection->isInitialized($instance) ? $reflection->getValue($instance) : null;
    }

    private function setEmbeddedProperty(object $instance, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setValue($instance, $value);
    }

    public function getCreatedAtColumn(): string
    {
        return 'created_at';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updated_at';
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function performInsert(): bool
    {
        if ($this->usesTimestamps()) {
            $time = $this->freshTimestampString();
            $this->setAttribute($this->getCreatedAtColumn(), $time);
            $this->setAttribute($this->getUpdatedAtColumn(), $time);
        }

        $attributes = $this->getAttributesForInsert();

        if ($attributes === []) {
            return true;
        }

        if ($this->isIncrementing()) {
            $id = $this->newQuery()->insertGetId($attributes, $this->getKeyName());
            $this->setAttribute($this->getKeyName(), $id);
        } else {
            $this->newQuery()->insert($attributes);
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    public function performUpdate(): bool
    {
        if (!$this->isDirty()) {
            return true;
        }

        if ($this->usesTimestamps()) {
            $this->setAttribute($this->getUpdatedAtColumn(), $this->freshTimestampString());
        }

        $versionProperty = $this->getVersionProperty();
        $versionValue = null;

        if ($versionProperty !== null) {
            $versionValue = $this->getAttribute($versionProperty);
            $this->setAttribute($versionProperty, $this->incrementVersionValue($versionValue));
        }

        $dirty = $this->getAttributesForUpdate();

        if ($dirty === []) {
            return true;
        }

        $query = $this->newQuery()->where($this->getKeyName(), '=', $this->getKey());

        if ($versionProperty !== null) {
            $query->where($versionProperty, '=', $versionValue);
        }

        $rows = $query->update($dirty);

        if ($versionProperty !== null && $rows === 0) {
            throw OptimisticLockException::fromEntity($this);
        }

        $this->syncOriginal();

        return true;
    }

    public function performDelete(): int
    {
        $this->exists = false;

        return $this->newQuery()->where($this->getKeyName(), '=', $this->getKey())->delete();
    }

    protected function getAttributesForInsert(): array
    {
        $primaryKey = $this->getKeyName();

        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if ($this->isIncrementing() && $key === $primaryKey && $value === null) {
                continue;
            }
            $attributes[$key] = $this->castToDatabase($key, $value);
        }

        return $attributes;
    }

    protected function getAttributesForUpdate(): array
    {
        $attributes = [];
        foreach ($this->getDirty() as $key => $value) {
            $attributes[$key] = $this->castToDatabase($key, $value);
        }

        return $attributes;
    }

    protected function castToPHP(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->getCasts()[$key] ?? null;

        if ($type === null) {
            return $value;
        }

        if (is_string($type) && class_exists($type) && in_array(CastsAttributes::class, class_implements($type) ?: [], true)) {
            return (new $type())->get($this, $key, $value, $this->attributes);
        }

        return match ((string) $type) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'array' => is_array($value) ? $value : (json_decode($value, true) ?? []),
            'json' => is_array($value) ? $value : (json_decode($value, true) ?? []),
            'object' => is_object($value) ? $value : (json_decode($value) ?? new \stdClass()),
            'collection' => is_array($value) ? $value : (json_decode($value, true) ?? []),
            'datetime' => $this->asDateTime($value),
            'date' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            default => $this->castEnumToPHP($type, $value) ?? $value,
        };
    }

    protected function castToDatabase(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->getCasts()[$key] ?? null;

        if ($type === null) {
            return $value;
        }

        if (is_string($type) && class_exists($type) && in_array(CastsAttributes::class, class_implements($type) ?: [], true)) {
            return (new $type())->set($this, $key, $value, $this->attributes);
        }

        return match ((string) $type) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => $value ? 1 : 0,
            'array', 'json', 'collection' => $this->fromJson($value),
            'object' => $this->fromJson($value),
            'datetime', 'timestamp' => $this->fromDateTime($value),
            'date' => $this->fromDateTime($value, 'Y-m-d'),
            default => $this->castEnumToDatabase($type, $value) ?? $value,
        };
    }

    protected function castToArray(string $key, mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($this->dateFormat);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        $type = $this->getCasts()[$key] ?? null;

        if ($type === 'date') {
            return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $value;
    }

    protected function castRelationToArray(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $item instanceof self ? $item->toArray() : $item, $value);
        }

        return $value;
    }

    protected function castEnumToPHP(string $type, mixed $value): mixed
    {
        if (!enum_exists($type)) {
            return null;
        }

        if ($value instanceof $type) {
            return $value;
        }

        if (is_subclass_of($type, BackedEnum::class) && is_int($value) || is_string($value)) {
            return $type::tryFrom($value);
        }

        return $value;
    }

    protected function castEnumToDatabase(string $type, mixed $value): mixed
    {
        if (!enum_exists($type)) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    protected function asDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value) || (is_string($value) && ctype_digit((string) $value))) {
            return (new DateTimeImmutable())->setTimestamp((int) $value);
        }

        $date = DateTimeImmutable::createFromFormat($this->dateFormat, (string) $value);
        if ($date !== false) {
            return $date;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    protected function asTimestamp(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        $date = $this->asDateTime($value);

        return $date?->getTimestamp();
    }

    protected function fromDateTime(mixed $value, ?string $format = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($format ?? $this->dateFormat);
        }

        if (is_int($value)) {
            return date($format ?? $this->dateFormat, $value);
        }

        return $value;
    }

    protected function fromJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value) ?: '[]';
    }

    protected function freshTimestampString(): string
    {
        return date($this->dateFormat);
    }

    protected function getClassBasename(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getMorphClass(): string
    {
        return static::class;
    }

    protected function pluralize(string $word): string
    {
        if (str_ends_with($word, 'ss') || str_ends_with($word, 'x') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh') || str_ends_with($word, 'z')) {
            return $word . 'es';
        }

        if (str_ends_with($word, 'y') && !in_array(substr($word, -2, 1), ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($word, 0, -1) . 'ies';
        }

        return $word . 's';
    }

    protected function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?: $value);
    }

    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->getKeyName();

        return new HasOne($this, $related, $foreignKey, $localKey, $this->getRelationCaller());
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= $this->getForeignKey();
        $localKey ??= $this->getKeyName();

        return new HasMany($this, $related, $foreignKey, $localKey, $this->getRelationCaller());
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey ??= $this->snake($instance->getClassBasename()) . '_' . ($ownerKey ?? $instance->getKeyName());
        $ownerKey ??= $instance->getKeyName();

        return new BelongsTo($this, $related, $foreignKey, $ownerKey, $this->getRelationCaller());
    }

    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        return new BelongsToMany($this, $related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $this->getRelationCaller());
    }

    public function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphOne
    {
        return new MorphOne($this, $related, $name, $type, $id, $localKey);
    }

    public function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphMany
    {
        return new MorphMany($this, $related, $name, $type, $id, $localKey);
    }

    public function morphTo(string $name, ?string $type = null, ?string $id = null, ?string $ownerKey = null): MorphTo
    {
        return new MorphTo($this, $name, $type, $id, $ownerKey);
    }

    protected function getRelationCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $trace[2]['function'] ?? 'relation';
    }

    protected static function bootIfNotBooted(): void
    {
        if (isset(self::$booted[static::class])) {
            return;
        }

        self::$booted[static::class] = true;
        static::bootTraits();
    }

    protected static function bootTraits(): void
    {
        foreach (class_uses(static::class) as $trait) {
            $method = 'boot' . (substr((string) strrchr($trait, '\\'), 1) ?: $trait);
            if (method_exists(static::class, $method)) {
                static::$method();
            }
        }
    }

    public static function addGlobalScope(Scope $scope): void
    {
        self::$globalScopes[static::class][] = $scope;
    }

    /**
     * @return list<Scope>
     */
    public static function getGlobalScopes(): array
    {
        return self::$globalScopes[static::class] ?? [];
    }
}
