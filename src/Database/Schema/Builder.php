<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Schema;

use Closure;
use TondbadSwoole\Database\ConnectionInterface;

class Builder
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);
        $callback($blueprint);

        $this->build($blueprint);
    }

    public function table(string $table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);
        $callback($blueprint);

        $this->build($blueprint);
    }

    public function drop(string $table): void
    {
        $this->connection->statement($this->getGrammar()->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement($this->getGrammar()->compileDropIfExists($table));
    }

    public function hasTable(string $table): bool
    {
        $results = $this->connection->select($this->getGrammar()->compileHasTable($table));

        return count($results) > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $results = $this->connection->select($this->getGrammar()->compileHasColumn($table, $column));

        foreach ($results as $row) {
            foreach ($row as $key => $value) {
                if (strtolower((string) $key) === 'name' && strtolower((string) $value) === strtolower($column)) {
                    return true;
                }
                if (strtolower((string) $key) === 'column_name' && strtolower((string) $value) === strtolower($column)) {
                    return true;
                }
                if (strtolower((string) $key) === 'field' && strtolower((string) $value) === strtolower($column)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function rename(string $from, string $to): void
    {
        $this->connection->statement($this->getGrammar()->compileRename($from, $to));
    }

    public function getTables(): array
    {
        return $this->connection->select($this->getGrammar()->compileGetTables());
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    protected function createBlueprint(string $table): Blueprint
    {
        return new Blueprint($table);
    }

    protected function build(Blueprint $blueprint): void
    {
        foreach ($this->getGrammar()->compileCreate($blueprint) as $statement) {
            $this->connection->statement($statement);
        }
    }

    private function getGrammar(): \TondbadSwoole\Database\Query\Grammar
    {
        return $this->connection->getGrammar();
    }
}
