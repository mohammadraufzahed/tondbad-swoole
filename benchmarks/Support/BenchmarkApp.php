<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Database\Migrations\Migrator;
use TondbadSwoole\Tests\Support\DatabaseContainer;

final class BenchmarkApp
{
    private static ?App $app = null;

    private static bool $mysqlStarted = false;

    public static function boot(): App
    {
        if (self::$app !== null) {
            return self::$app;
        }

        $env = self::baseEnv();

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

    public static function stop(): void
    {
        if (self::$mysqlStarted) {
            DatabaseContainer::stop();
            self::$mysqlStarted = false;
        }

        self::$app = null;
    }

    /**
     * @return array<string, string>
     */
    private static function baseEnv(): array
    {
        $useMysql = self::wantsMysql() && extension_loaded('pdo_mysql');

        $env = [
            'APP_TYPE' => 'http',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => $useMysql ? 'mysql' : 'sqlite',
            'CACHE_DEFAULT' => 'in-memory',
            'CACHE_IN_MEMORY_CLEAN_INTERVAL' => '0',
            'QUEUE_DEFAULT' => 'database',
            'AUTH_GUARD' => 'access_token',
            'AUTH_SESSION_STORE' => 'database',
        ];

        if ($useMysql) {
            $env = array_merge($env, self::mysqlEnv());
        } else {
            $env['DB_SQLITE_DATABASE'] = ':memory:';
        }

        return $env;
    }

    private static function wantsMysql(): bool
    {
        return getenv('BENCHMARK_MYSQL') === '1';
    }

    /**
     * @return array<string, string>
     */
    private static function mysqlEnv(): array
    {
        if (!self::$mysqlStarted && class_exists(DatabaseContainer::class)) {
            putenv('RUN_INTEGRATION_TESTS=1');
            $_ENV['RUN_INTEGRATION_TESTS'] = '1';

            try {
                DatabaseContainer::startMysql();

                if (self::waitForMysql(30)) {
                    self::$mysqlStarted = true;
                    register_shutdown_function([self::class, 'stop']);
                } else {
                    fwrite(STDERR, "MySQL container did not become ready in time. Falling back to SQLite.\n");
                    DatabaseContainer::stop();
                }
            } catch (\Throwable $e) {
                fwrite(STDERR, "MySQL container unavailable ({$e->getMessage()}). Falling back to SQLite.\n");
            }
        }

        if (!self::$mysqlStarted) {
            fwrite(STDERR, "MySQL benchmark requested but Testcontainers/PDO MySQL unavailable. Falling back to SQLite.\n");

            return ['DB_CONNECTION' => 'sqlite', 'DB_SQLITE_DATABASE' => ':memory:'];
        }

        $config = DatabaseContainer::mysqlConfig();

        return [
            'DB_MYSQL_HOST' => $config['host'],
            'DB_MYSQL_PORT' => (string) $config['port'],
            'DB_MYSQL_DATABASE' => $config['database'],
            'DB_MYSQL_USERNAME' => $config['username'],
            'DB_MYSQL_PASSWORD' => $config['password'],
            'DB_MYSQL_CHARSET' => $config['charset'],
        ];
    }

    private static function waitForMysql(int $timeoutSeconds): bool
    {
        $config = DatabaseContainer::mysqlConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        $start = microtime(true);

        while ((microtime(true) - $start) < $timeoutSeconds) {
            try {
                $pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->query('SELECT 1');

                return true;
            } catch (PDOException) {
                usleep(200000);
            }
        }

        return false;
    }
}
