<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Database\Migrations\Migrator;

final class BenchmarkApp
{
    private static ?App $app = null;

    public static function boot(): App
    {
        if (self::$app !== null) {
            return self::$app;
        }

        $env = [
            'APP_TYPE' => 'http',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_SQLITE_DATABASE' => ':memory:',
            'CACHE_DEFAULT' => 'in-memory',
            'QUEUE_DEFAULT' => 'database',
            'AUTH_GUARD' => 'access_token',
            'AUTH_SESSION_STORE' => 'database',
        ];

        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$app = AppFactory::create()->boot();

        return self::$app;
    }

    public static function migrate(): void
    {
        self::boot()->container->make(Migrator::class)->run();
    }

    public static function reset(): void
    {
        self::$app = null;
    }
}
