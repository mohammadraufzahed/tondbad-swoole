<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines;

use TondbadSwoole\Database\Contracts\DatabaseFeatures;
use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\Features\MySqlFeatures;
use TondbadSwoole\Database\Operations\MySqlOperations;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Query\Grammars\MySqlGrammar;

class MySqlEngine extends AbstractDatabaseEngine
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getOperations(): DatabaseOperations
    {
        return new MySqlOperations();
    }

    public function getFeatures(): DatabaseFeatures
    {
        return new MySqlFeatures();
    }

    public function getGrammar(): Grammar
    {
        return new MySqlGrammar();
    }

    protected function createDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }
}
