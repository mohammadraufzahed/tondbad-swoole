<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines;

use TondbadSwoole\Database\Contracts\DatabaseFeatures;
use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\Features\SqliteFeatures;
use TondbadSwoole\Database\Operations\SqliteOperations;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Query\Grammars\SqliteGrammar;

class SqliteEngine extends AbstractDatabaseEngine
{
    public function getName(): string
    {
        return 'sqlite';
    }

    public function getOperations(): DatabaseOperations
    {
        return new SqliteOperations();
    }

    public function getFeatures(): DatabaseFeatures
    {
        return new SqliteFeatures();
    }

    public function getGrammar(): Grammar
    {
        return new SqliteGrammar();
    }

    protected function createDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';

        return "sqlite:{$database}";
    }
}
