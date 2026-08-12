<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Support;

use PDO;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForExec;
use Testcontainers\Wait\WaitForLog;

class DatabaseContainer
{
    private static ?StartedGenericContainer $mysql = null;

    private static ?StartedGenericContainer $postgres = null;

    public static function enabled(?string $driver = null): bool
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            return false;
        }

        if ($driver === null) {
            return true;
        }

        $extension = match ($driver) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            default => 'pdo_' . $driver,
        };

        return extension_loaded($extension);
    }

    public static function startMysql(): void
    {
        if (!self::enabled('mysql') || self::$mysql !== null) {
            return;
        }

        self::$mysql = (new GenericContainer('mysql:8.0'))
            ->withExposedPorts(3306)
            ->withEnvironment([
                'MYSQL_ROOT_PASSWORD' => 'secret',
                'MYSQL_DATABASE' => 'tondbad',
            ])
            ->withWait(new WaitForLog('ready for connections'))
            ->start();
    }

    public static function startPostgres(): void
    {
        if (!self::enabled('pgsql') || self::$postgres !== null) {
            return;
        }

        self::$postgres = (new GenericContainer('postgres:15'))
            ->withExposedPorts(5432)
            ->withEnvironment([
                'POSTGRES_USER' => 'postgres',
                'POSTGRES_PASSWORD' => 'secret',
                'POSTGRES_DB' => 'tondbad',
            ])
            ->withWait(new WaitForExec(['pg_isready']))
            ->start();
    }

    public static function stop(): void
    {
        self::$mysql?->stop();
        self::$mysql = null;

        self::$postgres?->stop();
        self::$postgres = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mysqlConfig(): array
    {
        if (self::$mysql === null) {
            throw new \RuntimeException('MySQL container has not been started.');
        }

        return [
            'driver' => 'mysql',
            'host' => self::$mysql->getHost(),
            'port' => self::$mysql->getMappedPort(3306),
            'database' => 'tondbad',
            'username' => 'root',
            'password' => 'secret',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function postgresConfig(): array
    {
        if (self::$postgres === null) {
            throw new \RuntimeException('PostgreSQL container has not been started.');
        }

        return [
            'driver' => 'pgsql',
            'host' => self::$postgres->getHost(),
            'port' => self::$postgres->getMappedPort(5432),
            'database' => 'tondbad',
            'username' => 'postgres',
            'password' => 'secret',
            'charset' => 'utf8',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];
    }
}
