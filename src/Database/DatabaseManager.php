<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\Engines\EngineFactory;
use TondbadSwoole\Database\Engines\Contracts\DatabaseEngine;
use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Support\Context;

class DatabaseManager
{
    /**
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    private readonly Config $config;

    private readonly ContextInterface $context;

    private readonly EngineFactory $engineFactory;

    public function __construct(Config $config, ?ContextInterface $context = null, ?EngineFactory $engineFactory = null)
    {
        $this->config = $config;
        $this->context = $context ?? new Context();
        $this->engineFactory = $engineFactory ?? new EngineFactory();
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name ??= $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    public function table(string $table, ?string $as = null): Builder
    {
        return $this->connection()->table($table, $as);
    }

    public function query(): Builder
    {
        return $this->connection()->query();
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('database.default', 'mysql');
    }

    /**
     * Register a custom database engine for a driver alias.
     *
     * @param class-string<DatabaseEngine> $engineClass
     */
    public function extend(string $driver, string $engineClass): void
    {
        $this->engineFactory->extend($driver, $engineClass);
    }

    public function closeOldConnections(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }

    private function makeConnection(string $name): ConnectionInterface
    {
        $config = $this->config->get('database.connections.' . $name, []);

        if (!is_array($config)) {
            throw new \RuntimeException("Database connection [{$name}] is not configured.");
        }

        $engine = $this->engineFactory->makeForConfig($config);
        $pool = $this->createPool($engine, $config);

        return $engine->createConnection($pool, $this->context, $name);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPool(DatabaseEngine $engine, array $config): PoolInterface
    {
        $poolConfig = $config['pool'] ?? [];
        $factory = function () use ($engine, $config): \PDO {
            return $engine->createPdo($config);
        };

        $min = (int) ($poolConfig['min'] ?? 0);
        $max = (int) ($poolConfig['max'] ?? 10);
        $waitTimeout = (float) ($poolConfig['wait_timeout'] ?? 3.0);

        $runtimeConfig = [
            'max_age' => (float) ($poolConfig['max_age'] ?? 0.0),
            'max_usage' => (int) ($poolConfig['max_usage'] ?? 0),
            'health_check' => (bool) ($poolConfig['health_check'] ?? false),
            'check_interval' => (float) ($poolConfig['check_interval'] ?? 30.0),
        ];

        if (class_exists(\OpenSwoole\Coroutine\Channel::class) && $max > 1) {
            return new SwoolePdoPool($factory, $min, $max, $waitTimeout, $runtimeConfig);
        }

        return new SimplePdoPool($factory, $runtimeConfig);
    }
}
