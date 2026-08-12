<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Operations;

use TondbadSwoole\Database\Contracts\DatabaseOperations;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Column;
use TondbadSwoole\Database\Schema\ForeignKey;
use TondbadSwoole\Database\Schema\Index;

abstract class AbstractOperations implements DatabaseOperations
{
    abstract public function getQuoteCharacter(): string;

    public function wrapIdentifier(string $value): string
    {
        $quote = $this->getQuoteCharacter();

        if ($value === '*') {
            return '*';
        }

        return $quote . str_replace($quote, $quote . $quote, $value) . $quote;
    }

    public function quoteString(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }

    public function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->quoteString((string) $value);
    }

    public function getType(Column $column): string
    {
        return match ($column->type) {
            'integer', 'mediumInteger', 'tinyInteger', 'smallInteger' => 'int',
            'bigInteger' => 'bigint',
            'string' => 'varchar(' . ($column->length ?? 255) . ')',
            'char' => 'char(' . ($column->length ?? 1) . ')',
            'text' => 'text',
            'mediumText' => 'mediumtext',
            'longText' => 'longtext',
            'boolean' => 'tinyint(1)',
            'json', 'jsonb' => 'json',
            'datetime' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'timestamp' => 'timestamp',
            'enum' => 'enum(' . implode(', ', array_map(fn ($v) => $this->quoteString((string) $v), $column->allowed ?? [])) . ')',
            'decimal' => 'decimal(' . ($column->total ?? 8) . ', ' . ($column->places ?? 2) . ')',
            'float' => 'float',
            'double' => 'double',
            'binary' => 'blob',
            default => 'varchar(255)',
        };
    }

    public function getColumnSql(Column $column): string
    {
        $sql = $this->wrapIdentifier($column->name) . ' ' . $this->getType($column);

        if ($column->unsigned) {
            $sql .= ' unsigned';
        }

        if (!$column->nullable && $column->default === null && !$column->autoIncrement) {
            $sql .= ' not null';
        } elseif ($column->nullable) {
            $sql .= ' null';
        } else {
            $sql .= ' not null';
        }

        if ($column->autoIncrement) {
            $sql .= ' auto_increment';
        }

        if ($column->primary) {
            $sql .= ' primary key';
        }

        if ($column->useCurrent) {
            $sql .= ' default CURRENT_TIMESTAMP';
        } elseif ($column->default !== null) {
            $sql .= ' default ' . $this->formatDefault($column->default);
        }

        if ($column->comment !== null) {
            $sql .= " comment '" . addslashes($column->comment) . "'";
        }

        if ($column->collation !== null) {
            $sql .= ' collate ' . $column->collation;
        }

        return $sql;
    }

    public function getIndexSql(Index $index, string $table): string
    {
        if ($index->type === 'dropIndex') {
            return 'drop index ' . $this->wrapIdentifier($index->name) . ' on ' . $this->wrapIdentifier($table);
        }

        if ($index->type === 'dropUnique') {
            return 'drop index ' . $this->wrapIdentifier($index->name) . ' on ' . $this->wrapIdentifier($table);
        }

        if ($index->type === 'dropPrimary') {
            return 'alter table ' . $this->wrapIdentifier($table) . ' drop primary key';
        }

        $columns = implode(', ', array_map([$this, 'wrapIdentifier'], $index->columns));

        return match ($index->type) {
            'primary' => 'primary key (' . $columns . ')',
            'unique' => 'constraint ' . $this->wrapIdentifier($index->name) . ' unique (' . $columns . ')',
            default => 'index ' . $this->wrapIdentifier($index->name) . ' (' . $columns . ')',
        };
    }

    public function getForeignKeySql(ForeignKey $foreignKey): string
    {
        $columns = implode(', ', array_map([$this, 'wrapIdentifier'], $foreignKey->columns));
        $references = implode(', ', array_map([$this, 'wrapIdentifier'], $foreignKey->references));

        $sql = 'constraint ' . $this->wrapIdentifier($foreignKey->name) . ' foreign key (' . $columns . ') references ' . $this->wrapIdentifier($foreignKey->on) . ' (' . $references . ')';

        if ($foreignKey->onDelete !== null) {
            $sql .= ' on delete ' . $foreignKey->onDelete;
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= ' on update ' . $foreignKey->onUpdate;
        }

        return $sql;
    }

    public function compileCreateSuffix(Blueprint $blueprint): string
    {
        $sql = '';

        if ($blueprint->engine !== null) {
            $sql .= ' engine=' . $blueprint->engine;
        }

        if ($blueprint->charset !== null) {
            $sql .= ' default charset=' . $blueprint->charset;
        }

        if ($blueprint->collation !== null) {
            $sql .= ' collate=' . $blueprint->collation;
        }

        if ($blueprint->comment !== null) {
            $sql .= " comment '" . addslashes($blueprint->comment) . "'";
        }

        return $sql;
    }

    public function compileDropIfExists(string $table): string
    {
        return 'drop table if exists ' . $this->wrapIdentifier($table);
    }

    public function compileHasTable(string $table): string
    {
        return 'show tables like ' . $this->quoteString($table);
    }

    public function compileHasColumn(string $table, string $column): string
    {
        return 'show columns from ' . $this->wrapIdentifier($table) . ' where Field = ' . $this->quoteString($column);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'rename table ' . $this->wrapIdentifier($from) . ' to ' . $this->wrapIdentifier($to);
    }

    public function compileGetTables(): string
    {
        return 'show tables';
    }

    public function compileTruncate(string $table): string
    {
        return 'truncate table ' . $this->wrapIdentifier($table);
    }

    public function compileAddColumn(string $table, Column $column): string
    {
        return 'alter table ' . $this->wrapIdentifier($table) . ' add column ' . $this->getColumnSql($column);
    }

    public function getHealthCheckSql(): string
    {
        return 'SELECT 1';
    }
}
