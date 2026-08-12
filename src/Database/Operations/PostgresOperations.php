<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Operations;

use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Column;
use TondbadSwoole\Database\Schema\Index;

class PostgresOperations extends AbstractOperations
{
    public function getQuoteCharacter(): string
    {
        return '"';
    }

    public function getType(Column $column): string
    {
        return match ($column->type) {
            'integer', 'mediumInteger' => 'integer',
            'bigInteger' => 'bigint',
            'smallInteger', 'tinyInteger' => 'smallint',
            'string' => 'varchar(' . ($column->length ?? 255) . ')',
            'char' => 'char(' . ($column->length ?? 1) . ')',
            'text', 'mediumText', 'longText' => 'text',
            'boolean' => 'boolean',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'datetime' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'timestamp' => 'timestamp',
            'enum' => 'varchar(255)',
            'decimal' => 'decimal(' . ($column->total ?? 8) . ', ' . ($column->places ?? 2) . ')',
            'float' => 'real',
            'double' => 'double precision',
            'binary' => 'bytea',
            default => 'text',
        };
    }

    public function getColumnSql(Column $column): string
    {
        $sql = $this->wrapIdentifier($column->name) . ' ' . $this->getType($column);

        if (!$column->nullable && $column->default === null && !$column->autoIncrement) {
            $sql .= ' not null';
        } elseif ($column->nullable) {
            $sql .= ' null';
        } else {
            $sql .= ' not null';
        }

        if ($column->autoIncrement) {
            $sql .= ' generated always as identity primary key';
        } elseif ($column->primary) {
            $sql .= ' primary key';
        }

        if ($column->useCurrent) {
            $sql .= ' default CURRENT_TIMESTAMP';
        } elseif ($column->default !== null) {
            $sql .= ' default ' . $this->formatDefault($column->default);
        }

        return $sql;
    }

    public function getIndexSql(Index $index, string $table): string
    {
        if ($index->type === 'dropIndex' || $index->type === 'dropUnique' || $index->type === 'dropPrimary') {
            return 'drop index if exists ' . $this->wrapIdentifier($index->name);
        }

        $columns = implode(', ', array_map([$this, 'wrapIdentifier'], $index->columns));

        return match ($index->type) {
            'primary' => 'primary key (' . $columns . ')',
            'unique' => 'constraint ' . $this->wrapIdentifier($index->name) . ' unique (' . $columns . ')',
            default => 'index ' . $this->wrapIdentifier($index->name) . ' (' . $columns . ')',
        };
    }

    public function compileCreateSuffix(Blueprint $blueprint): string
    {
        return '';
    }

    public function compileDropIfExists(string $table): string
    {
        return 'drop table if exists ' . $this->wrapIdentifier($table);
    }

    public function compileHasTable(string $table): string
    {
        return "select * from information_schema.tables where table_schema = 'public' and table_name = " . $this->quoteString($table);
    }

    public function compileHasColumn(string $table, string $column): string
    {
        return "select * from information_schema.columns where table_name = " . $this->quoteString($table) . " and column_name = " . $this->quoteString($column);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'alter table ' . $this->wrapIdentifier($from) . ' rename to ' . $this->wrapIdentifier($to);
    }

    public function compileGetTables(): string
    {
        return "select table_name as name from information_schema.tables where table_schema = 'public'";
    }

    public function compileTruncate(string $table): string
    {
        return 'truncate table ' . $this->wrapIdentifier($table);
    }

    public function getHealthCheckSql(): string
    {
        return 'SELECT 1';
    }
}
