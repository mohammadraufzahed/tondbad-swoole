<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Criteria;

final class Restrictions
{
    public static function eq(string $field, mixed $value): array
    {
        return [$field, '=', $value];
    }

    public static function ne(string $field, mixed $value): array
    {
        return [$field, '!=', $value];
    }

    public static function gt(string $field, mixed $value): array
    {
        return [$field, '>', $value];
    }

    public static function gte(string $field, mixed $value): array
    {
        return [$field, '>=', $value];
    }

    public static function lt(string $field, mixed $value): array
    {
        return [$field, '<', $value];
    }

    public static function lte(string $field, mixed $value): array
    {
        return [$field, '<=', $value];
    }

    public static function like(string $field, string $value): array
    {
        return [$field, 'like', $value];
    }

    public static function notLike(string $field, string $value): array
    {
        return [$field, 'not like', $value];
    }

    public static function in(string $field, array $values): array
    {
        return [$field, 'in', $values];
    }

    public static function notIn(string $field, array $values): array
    {
        return [$field, 'not in', $values];
    }

    public static function isNull(string $field): array
    {
        return [$field, 'is null', null];
    }

    public static function isNotNull(string $field): array
    {
        return [$field, 'is not null', null];
    }

    public static function between(string $field, mixed $min, mixed $max): array
    {
        return [$field, 'between', [$min, $max]];
    }
}
