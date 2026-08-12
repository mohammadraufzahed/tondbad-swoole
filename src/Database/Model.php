<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use TondbadSwoole\Database\Casts\CastsAttributes;
use TondbadSwoole\Database\Relations\BelongsTo;
use TondbadSwoole\Database\Relations\HasMany;
use TondbadSwoole\Database\Relations\HasOne;
use TondbadSwoole\Database\Relations\Relation;
use TondbadSwoole\Routing\Contracts\UrlRoutable;
use UnitEnum;

abstract class Model implements UrlRoutable
{
    protected ?string $table = null;

    protected string $primaryKey = 'id';

    protected bool $incrementing = true;

    protected string $keyType = 'int';

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
    }

    public static function query(): ModelBuilder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): ModelBuilder
    {
        $connection = $this->getConnection();

        return (new ModelBuilder($connection, $connection->getGrammar()))
            ->from($this->getTable())
            ->setModel(static::class);
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

    public static function count(string $column = '*'): int
    {
        return static::query()->count($column);
    }

    public static function with(array|string $relations): ModelBuilder
    {
        return static::query()->with($relations);
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
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getQualifiedKeyName(): string
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }

    public function getForeignKey(): string
    {
        return $this->snake($this->getClassBasename()) . '_' . $this->primaryKey;
    }

    public function getRouteKey(): mixed
    {
        return $this->getKey();
    }

    public function getRouteKeyName(): string
    {
        return $this->primaryKey;
    }

    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::firstWhere([$field => $value]);
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
        return $this->incrementing;
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
        if (array_key_exists($key, $this->attributes)) {
            return $this->castToPHP($key, $this->attributes[$key]);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): self
    {
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

    public function delete(): int
    {
        if (!$this->exists) {
            return 0;
        }

        $this->exists = false;

        return $this->newQuery()->where($this->primaryKey, $this->getKey())->delete();
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

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
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
        return array_key_exists($key, $this->attributes) || array_key_exists($key, $this->relations);
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

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getCasts(): array
    {
        if ($this->getIncrementing()) {
            return array_merge($this->casts, [$this->getKeyName() => $this->getKeyType()]);
        }

        return $this->casts;
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

    protected function performInsert(): bool
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

        $id = $this->newQuery()->insertGetId($attributes, $this->primaryKey);

        if ($this->isIncrementing()) {
            $this->setAttribute($this->primaryKey, $id);
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    protected function performUpdate(): bool
    {
        if (!$this->isDirty()) {
            return true;
        }

        if ($this->usesTimestamps()) {
            $this->setAttribute($this->getUpdatedAtColumn(), $this->freshTimestampString());
        }

        $dirty = $this->getAttributesForUpdate();

        if ($dirty === []) {
            return true;
        }

        $this->newQuery()->where($this->primaryKey, $this->getKey())->update($dirty);
        $this->syncOriginal();

        return true;
    }

    protected function getAttributesForInsert(): array
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if ($this->isIncrementing() && $key === $this->primaryKey && $value === null) {
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

    protected function fromDateTime(mixed $value, string $format = null): mixed
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

    protected function getRelationCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $trace[2]['function'] ?? 'relation';
    }
}
