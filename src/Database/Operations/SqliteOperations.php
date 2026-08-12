<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Operations;

use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Column;
use TondbadSwoole\Database\Schema\Index;

class SqliteOperations extends AbstractOperations
{
    public function getQuoteCharacter(): string
    {
        return '"';
    }

    public function getType(Column $column): string
    {
        return match ($column->type) {
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'mediumInteger' => 'integer',
            'string', 'char', 'text', 'mediumText', 'longText' => 'text',
            'boolean' => 'integer',
            'json', 'jsonb' => 'text',
            'datetime', 'timestamp', 'date', 'time' => 'text',
            'enum' => 'text',
            'decimal', 'float', 'double' => 'real',
            'binary' => 'blob',
            default => 'text',
        };
    }

    public function getColumnSql(Column $column): string
    {
        $sql = $this->wrapIdentifier($column->name) . ' ' . $this->getType($column);

        if ($column->primary || $column->autoIncrement) {
            $sql .= ' primary key';
            if ($column->autoIncrement) {
                $sql .= ' autoincrement';
            }
        }

        if ($column->nullable) {
            $sql .= ' null';
        } elseif (!$column->primary && !$column->autoIncrement) {
            $sql .= ' not null';
        }

        if ($column->default !== null) {
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

    public function compileHasTable(string $table): string
    {
        return "select name from sqlite_master where type = 'table' and name = " . $this->quoteString($table);
    }

    public function compileHasColumn(string $table, string $column): string
    {
        return 'pragma table_info(' . $this->wrapIdentifier($table) . ')';
    }

    public function compileRename(string $from, string $to): string
    {
        return 'alter table ' . $this->wrapIdentifier($from) . ' rename to ' . $this->wrapIdentifier($to);
    }

    public function compileGetTables(): string
    {
        return "select name as name from sqlite_master where type = 'table' and name not like 'sqlite_%'";
    }

    public function compileTruncate(string $table): string
    {
        return 'delete from ' . $this->wrapIdentifier($table);
    }

    public function getHealthCheckSql(): string
    {
        return 'SELECT 1';
    }
}
