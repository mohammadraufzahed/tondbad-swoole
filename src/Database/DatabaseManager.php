<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\Query\Builder;

class DatabaseManager
{
    /**
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    public function __construct(private readonly Config $config)
    {
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

    private function makeConnection(string $name): ConnectionInterface
    {
        $config = $this->config->get('database.connections.' . $name, []);

        if (!is_array($config)) {
            throw new \RuntimeException("Database connection [{$name}] is not configured.");
        }

        return (new ConnectionFactory())->make($config, $name);
    }
}
