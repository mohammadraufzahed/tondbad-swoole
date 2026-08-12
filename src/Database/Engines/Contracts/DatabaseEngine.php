<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines\Contracts;

use PDO;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\Contracts\DatabaseFeatures;
use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\PoolInterface;
use TondbadSwoole\Database\Query\Grammar;

interface DatabaseEngine
{
    public function getName(): string;

    public function getOperations(): DatabaseOperations;

    public function getFeatures(): DatabaseFeatures;

    public function getGrammar(): Grammar;

    public function createPdo(array $config): PDO;

    public function createConnection(PoolInterface $pool, ContextInterface $context, string $name): ConnectionInterface;
}
