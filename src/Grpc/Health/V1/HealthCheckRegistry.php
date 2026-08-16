<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Health\V1;

final class HealthCheckRegistry
{
    /** @var array<string, callable> */
    private static array $checks = [];

    public static function set(string $service, callable $check): void
    {
        self::$checks[$service] = $check;
    }

    public static function remove(string $service): void
    {
        unset(self::$checks[$service]);
    }

    public static function clear(): void
    {
        self::$checks = [];
    }

    public static function status(string $service = ''): int
    {
        $checks = $service === '' ? self::$checks : array_intersect_key(self::$checks, [$service => true]);

        foreach ($checks as $name => $check) {
            try {
                if ($check() !== true) {
                    return HealthCheckResponse\ServingStatus::NOT_SERVING;
                }
            } catch (\Throwable) {
                return HealthCheckResponse\ServingStatus::NOT_SERVING;
            }
        }

        return HealthCheckResponse\ServingStatus::SERVING;
    }
}
