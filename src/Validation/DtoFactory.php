<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Attributes\Field;

final class DtoFactory
{
    /**
     * @param class-string $class
     */
    public static function make(string $class, array $data, ?DatabaseManager $databaseManager = null, bool $strict = false): object
    {
        $schema = self::schema($class, $strict);
        $result = $schema->safeParse($data, $databaseManager);

        if (!$result->valid) {
            throw ValidationException::fromErrors($result->errors);
        }

        $validated = $result->data;

        return self::instantiate($class, $validated, $databaseManager, $strict);
    }

    /**
     * @param class-string $class
     */
    public static function schema(string $class, bool $strict = false): Schema
    {
        $reflection = new ReflectionClass($class);
        $fields = self::collectFields($reflection, $strict);

        $schema = Schema::object($fields);

        return $strict ? $schema->strict() : $schema->lax();
    }

    /**
     * @return array<string, Schema>
     */
    private static function collectFields(ReflectionClass $reflection, bool $strict): array
    {
        $fields = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = self::getFieldAttribute($parameter);
            if ($field === null) {
                continue;
            }

            $fields[$parameter->getName()] = self::fieldSchema($parameter, $field, $strict);
        }

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->isPromoted()) {
                continue;
            }

            $field = self::getFieldAttribute($property);
            if ($field === null) {
                continue;
            }

            $fields[$property->getName()] = self::fieldSchema($property, $field, $strict);
        }

        return $fields;
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function getFieldAttribute(ReflectionParameter|ReflectionProperty $reflector): ?Field
    {
        $attributes = $reflector->getAttributes(Field::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function fieldSchema(ReflectionParameter|ReflectionProperty $reflector, Field $field, bool $strict): Schema
    {
        $type = $reflector->getType();
        $nestedClass = $field->nested ?? self::resolveClassType($type);

        if ($nestedClass !== null && class_exists($nestedClass)) {
            $schema = self::schema($nestedClass, $strict);
        } else {
            $schema = self::schemaFromType($type);
        }

        if ($field->alias !== null) {
            $schema = $schema->alias($field->alias);
        }

        if ($field->transform !== null) {
            foreach (array_map('trim', explode('|', $field->transform)) as $callable) {
                $schema = $schema->transform($callable);
            }
        }

        if ($field->rules !== null) {
            $schema = self::applyRules($schema, $field->rules);
        }

        if ($type?->allowsNull()) {
            $schema = $schema->nullable();
        }

        $default = self::resolveDefault($reflector, $field);
        $hasDefault = self::hasDefault($reflector, $field);

        if ($hasDefault) {
            $schema = $schema->default($default);
        } elseif ($type?->allowsNull()) {
            $schema = $schema->nullable();
        } elseif (self::isOptional($reflector)) {
            $schema = $schema->optional();
        }

        return $schema;
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function resolveDefault(ReflectionParameter|ReflectionProperty $reflector, Field $field): mixed
    {
        if ($field->default !== null) {
            return $field->default;
        }

        if ($reflector instanceof ReflectionParameter && $reflector->isDefaultValueAvailable()) {
            return $reflector->getDefaultValue();
        }

        if ($reflector instanceof ReflectionProperty && $reflector->hasDefaultValue()) {
            return $reflector->getDefaultValue();
        }

        return null;
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function hasDefault(ReflectionParameter|ReflectionProperty $reflector, Field $field): bool
    {
        if ($field->default !== null) {
            return true;
        }

        if ($reflector instanceof ReflectionParameter && $reflector->isDefaultValueAvailable()) {
            return true;
        }

        if ($reflector instanceof ReflectionProperty && $reflector->hasDefaultValue()) {
            return true;
        }

        return false;
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function isOptional(ReflectionParameter|ReflectionProperty $reflector): bool
    {
        if ($reflector instanceof ReflectionParameter && $reflector->isOptional()) {
            return true;
        }

        if ($reflector->getType()?->allowsNull()) {
            return true;
        }

        return false;
    }

    private static function schemaFromType(?\ReflectionType $type): Schema
    {
        if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
            return match ($type->getName()) {
                'string' => Schema::string(),
                'int' => Schema::int(),
                'float' => Schema::float(),
                'bool' => Schema::bool(),
                'array' => Schema::array(Schema::mixed()),
                'mixed' => Schema::mixed(),
                default => Schema::mixed(),
            };
        }

        return Schema::mixed();
    }

    private static function resolveClassType(?\ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
            return $type->getName();
        }

        return null;
    }

    private static function applyRules(Schema $schema, string $rules): Schema
    {
        $parts = array_map('trim', explode('|', $rules));

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            [$name, $params] = str_contains($part, ':') ? explode(':', $part, 2) : [$part, ''];
            $name = trim($name);
            $params = $params === '' ? [] : array_map('trim', explode(',', $params));

            $schema = match ($name) {
                'required' => $schema->required(),
                'nullable' => $schema->nullable(),
                'sometimes' => $schema->optional(),
                'string' => $schema->getType() === 'string' ? $schema : Schema::string(),
                'int', 'integer' => $schema->getType() === 'int' ? $schema : Schema::int(),
                'float', 'double' => $schema->getType() === 'float' ? $schema : Schema::float(),
                'bool', 'boolean' => $schema->getType() === 'bool' ? $schema : Schema::bool(),
                'array' => $schema->getType() === 'array' ? $schema : Schema::array(Schema::mixed()),
                'email' => $schema->email(),
                'url' => $schema->url(),
                'uuid' => $schema->uuid(),
                'ip' => $schema->ip(),
                'min' => $schema->min(self::parseNumber($params[0] ?? '0')),
                'max' => $schema->max(self::parseNumber($params[0] ?? '0')),
                'gt' => $schema->gt(self::parseNumber($params[0] ?? '0')),
                'gte' => $schema->gte(self::parseNumber($params[0] ?? '0')),
                'lt' => $schema->lt(self::parseNumber($params[0] ?? '0')),
                'lte' => $schema->lte(self::parseNumber($params[0] ?? '0')),
                'regex' => $schema->regex($params[0] ?? '/.*/'),
                'in' => $schema->in($params),
                'not_in' => $schema->notIn($params),
                'confirmed' => $schema->confirmed(),
                'unique' => $schema->unique($params[0] ?? '', $params[1] ?? null),
                'exists' => $schema->exists($params[0] ?? '', $params[1] ?? null),
                default => $schema,
            };
        }

        return $schema;
    }

    private static function parseNumber(string $value): int|float
    {
        if (is_numeric($value) && str_contains($value, '.')) {
            return (float) $value;
        }

        return (int) $value;
    }

    /**
     * @param class-string $class
     */
    private static function instantiate(string $class, array $data, ?DatabaseManager $databaseManager, bool $strict): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getDeclaringClass()->getName() === $class) {
            $args = [];

            foreach ($constructor->getParameters() as $parameter) {
                $field = self::getFieldAttribute($parameter);
                $name = $parameter->getName();
                $key = $field?->alias ?? $name;

                if (array_key_exists($name, $data)) {
                    $value = $data[$name];
                } elseif (array_key_exists($key, $data)) {
                    $value = $data[$key];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                } else {
                    $value = null;
                }

                $value = self::resolveNested($value, $parameter, $field, $databaseManager, $strict);
                $args[$name] = $value;
            }

            return $reflection->newInstance(...$args);
        }

        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $field = self::getFieldAttribute($property);
            if ($field === null) {
                continue;
            }

            $name = $property->getName();
            $key = $field->alias ?? $name;

            if (array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif (array_key_exists($key, $data)) {
                $value = $data[$key];
            } else {
                $value = self::resolveDefault($property, $field);
            }

            $value = self::resolveNested($value, $property, $field, $databaseManager, $strict);

            $property->setAccessible(true);
            $property->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * @param ReflectionParameter|ReflectionProperty $reflector
     */
    private static function resolveNested(mixed $value, ReflectionParameter|ReflectionProperty $reflector, ?Field $field, ?DatabaseManager $databaseManager, bool $strict): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $type = $reflector->getType();
        $nestedClass = $field?->nested ?? self::resolveClassType($type);

        if ($nestedClass !== null && class_exists($nestedClass)) {
            return self::make($nestedClass, $value, $databaseManager, $strict);
        }

        return $value;
    }
}
