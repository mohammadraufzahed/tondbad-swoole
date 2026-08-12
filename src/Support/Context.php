<?php

declare(strict_types=1);

namespace TondbadSwoole\Support;

class Context
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $storage = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $cid = self::cid();

        return self::$storage[$cid][$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $cid = self::cid();

        self::$storage[$cid][$key] = $value;
    }

    public static function delete(string $key): void
    {
        $cid = self::cid();

        unset(self::$storage[$cid][$key]);
    }

    public static function has(string $key): bool
    {
        $cid = self::cid();

        return isset(self::$storage[$cid][$key]);
    }

    public static function clear(): void
    {
        $cid = self::cid();

        unset(self::$storage[$cid]);
    }

    public static function clearAll(): void
    {
        self::$storage = [];
    }

    private static function cid(): int
    {
        if (class_exists(\OpenSwoole\Coroutine::class)) {
            return \OpenSwoole\Coroutine::getCid();
        }

        return 0;
    }
}
