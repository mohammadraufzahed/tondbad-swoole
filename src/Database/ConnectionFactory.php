<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;
use RuntimeException;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Query\Grammars\MySqlGrammar;
use TondbadSwoole\Database\Query\Grammars\PostgresGrammar;
use TondbadSwoole\Database\Query\Grammars\SqliteGrammar;

class ConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function make(array $config, string $name, ContextInterface $context): ConnectionInterface
    {
        $driver = $config['driver'] ?? 'mysql';
        $grammar = $this->createGrammar($driver);
        $grammar->setTablePrefix($config['prefix'] ?? null);
        $grammar->setDriver($driver);

        $factory = function () use ($config, $driver): PDO {
            $dsn = $this->createDsn($driver, $config);
            $username = $config['username'] ?? null;
            $password = $config['password'] ?? null;
            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            return new PDO($dsn, $username, $password, $options);
        };

        $pool = $this->createPool($factory, $config['pool'] ?? []);

        return new PdoConnection($pool, $grammar, $name, $context);
    }

    private function createGrammar(string $driver): Grammar
    {
        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'sqlite' => new SqliteGrammar(),
            'pgsql', 'postgres', 'postgresql' => new PostgresGrammar(),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDsn(string $driver, array $config): string
    {
        return match ($driver) {
            'mysql' => $this->createMysqlDsn($config),
            'sqlite' => $this->createSqliteDsn($config),
            'pgsql', 'postgres', 'postgresql' => $this->createPostgresDsn($config),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createMysqlDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createSqliteDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';

        return "sqlite:{$database}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPostgresDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? '';

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPool(\Closure $factory, array $config): PoolInterface
    {
        $min = (int) ($config['min'] ?? 1);
        $max = (int) ($config['max'] ?? 10);
        $waitTimeout = (float) ($config['wait_timeout'] ?? 3.0);

        if (class_exists(\OpenSwoole\Coroutine\Channel::class) && $max > 1) {
            return new SwoolePdoPool($factory, $min, $max, $waitTimeout);
        }

        return new SimplePdoPool($factory);
    }
}
