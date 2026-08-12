<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Contracts;

use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Column;
use TondbadSwoole\Database\Schema\ForeignKey;
use TondbadSwoole\Database\Schema\Index;

interface DatabaseOperations
{
    public function getQuoteCharacter(): string;

    public function wrapIdentifier(string $value): string;

    public function quoteString(string $value): string;

    public function formatDefault(mixed $value): string;

    public function getType(Column $column): string;

    public function getColumnSql(Column $column): string;

    public function getIndexSql(Index $index, string $wrappedTable): string;

    public function getForeignKeySql(ForeignKey $foreignKey): string;

    public function compileCreateSuffix(Blueprint $blueprint): string;

    public function compileDropIfExists(string $wrappedTable): string;

    public function compileHasTable(string $table): string;

    public function compileHasColumn(string $wrappedTable, string $column): string;

    public function compileRename(string $wrappedFrom, string $wrappedTo): string;

    public function compileGetTables(): string;

    public function compileTruncate(string $wrappedTable): string;

    public function compileAddColumn(string $wrappedTable, Column $column): string;

    public function getHealthCheckSql(): string;
}
