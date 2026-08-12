<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines;

use TondbadSwoole\Database\Contracts\DatabaseFeatures;
use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\Features\PostgresFeatures;
use TondbadSwoole\Database\Operations\PostgresOperations;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Query\Grammars\PostgresGrammar;

class PostgresEngine extends AbstractDatabaseEngine
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function getOperations(): DatabaseOperations
    {
        return new PostgresOperations();
    }

    public function getFeatures(): DatabaseFeatures
    {
        return new PostgresFeatures();
    }

    public function getGrammar(): Grammar
    {
        return new PostgresGrammar();
    }

    protected function createDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? '';

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }
}
