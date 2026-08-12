<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines;

use PDO;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\Contracts\DatabaseFeatures;
use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\DatabaseWrapper;
use TondbadSwoole\Database\Engines\Contracts\DatabaseEngine;
use TondbadSwoole\Database\PoolInterface;
use TondbadSwoole\Database\Query\Grammar;

abstract class AbstractDatabaseEngine implements DatabaseEngine
{
    public function createConnection(PoolInterface $pool, ContextInterface $context, string $name): ConnectionInterface
    {
        return new DatabaseWrapper($pool, $this->getGrammar(), $name, $context);
    }

    public function getGrammar(): Grammar
    {
        return new Grammar($this->getOperations(), $this->getFeatures());
    }

    abstract public function getOperations(): DatabaseOperations;

    abstract public function getFeatures(): DatabaseFeatures;

    /**
     * @param array<string, mixed> $config
     */
    public function createPdo(array $config): PDO
    {
        $dsn = $this->createDsn($config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (!is_string($username) && $username !== null) {
            $username = (string) $username;
        }

        if (!is_string($password) && $password !== null) {
            $password = (string) $password;
        }

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * @param array<string, mixed> $config
     */
    abstract protected function createDsn(array $config): string;
}
