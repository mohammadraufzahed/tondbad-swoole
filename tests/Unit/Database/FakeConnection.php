<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database;

use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;

class FakeConnection implements ConnectionInterface
{
    public string $lastSql = '';

    public array $lastBindings = [];

    public array $selectResult = [];

    public function __construct(
        private readonly Grammar $grammar,
        private readonly string $name = 'fake',
    ) {
    }

    public function table(string $table, ?string $as = null): Builder
    {
        return $this->query()->table($table, $as);
    }

    public function query(): Builder
    {
        return new Builder($this, $this->grammar);
    }

    public function select(string $sql, array $bindings = []): array
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return $this->selectResult;
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return true;
    }

    public function update(string $sql, array $bindings = []): int
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return 1;
    }

    public function delete(string $sql, array $bindings = []): int
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return 1;
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $this->lastSql = $sql;
        $this->lastBindings = $bindings;

        return true;
    }

    public function lastInsertId(?string $sequence = null): int|string
    {
        return 1;
    }

    public function transaction(callable $callback, int $attempts = 1): mixed
    {
        return $callback($this);
    }

    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }
}
