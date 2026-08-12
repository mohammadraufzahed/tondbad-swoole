<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;

interface ConnectionInterface
{
    public function table(string $table, ?string $as = null): Builder;

    public function query(): Builder;

    public function select(string $sql, array $bindings = []): array;

    public function insert(string $sql, array $bindings = []): bool;

    public function update(string $sql, array $bindings = []): int;

    public function delete(string $sql, array $bindings = []): int;

    public function statement(string $sql, array $bindings = []): bool;

    public function lastInsertId(?string $sequence = null): int|string;

    /**
     * @param callable(self): mixed $callback
     */
    public function transaction(callable $callback, int $attempts = 1): mixed;

    public function getGrammar(): Grammar;

    public function getName(): string;

    public function getSchemaBuilder(): SchemaBuilder;

    public function close(): void;
}
