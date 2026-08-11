<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Database\Schema\Column;
use TondbadSwoole\Database\Schema\ForeignKey;
use TondbadSwoole\Database\Schema\Index;

abstract class Grammar
{
    protected string $quoteCharacter = '"';

    protected ?string $tablePrefix = null;

    protected string $driver = 'mysql';

    public function setTablePrefix(?string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    public function getTablePrefix(): ?string
    {
        return $this->tablePrefix;
    }

    public function setDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function compileSelect(Builder $query): string
    {
        $components = [
            $this->compileColumns($query),
            $this->compileFrom($query),
            $this->compileJoins($query),
            $this->compileWheres($query),
            $this->compileGroups($query),
            $this->compileHavings($query),
            $this->compileOrders($query),
            $this->compileLimit($query),
            $this->compileOffset($query),
        ];

        return implode(' ', array_filter($components, fn ($part) => $part !== ''));
    }

    public function compileInsert(Builder $query, array $values): string
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Cannot insert empty values.');
        }

        $first = reset($values);

        if (!is_array($first)) {
            throw new InvalidArgumentException('Insert values must be an array of arrays.');
        }

        $columns = array_keys($first);
        $table = $this->wrapTable($query->from);
        $columnList = implode(', ', array_map([$this, 'wrap'], $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $rows = implode(', ', array_fill(0, count($values), "({$placeholders})"));

        return "insert into {$table} ({$columnList}) values {$rows}";
    }

    public function compileUpdate(Builder $query, array $values): string
    {
        if (empty($values)) {
            throw new InvalidArgumentException('Cannot update empty values.');
        }

        $table = $this->wrapTable($query->from);
        $columns = implode(', ', array_map(
            fn ($column) => $this->wrap($column) . ' = ?',
            array_keys($values)
        ));
        $wheres = $this->compileWheres($query);

        return rtrim("update {$table} set {$columns} {$wheres}");
    }

    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);
        $wheres = $this->compileWheres($query);

        return rtrim("delete from {$table} {$wheres}");
    }

    public function wrap($value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if ($value === '*') {
            return '*';
        }

        $value = (string) $value;

        if (str_contains($value, ' as ')) {
            [$base, $alias] = explode(' as ', $value, 2);

            return $this->wrap($base) . ' as ' . $this->wrapValue(trim($alias));
        }

        if (str_contains($value, '.')) {
            $parts = explode('.', $value);

            return implode('.', array_map(fn ($part) => $part === '*' ? '*' : $this->wrapValue($part), $parts));
        }

        return $this->wrapValue($value);
    }

    public function wrapTable($table): string
    {
        if ($table instanceof Expression) {
            return $table->getValue();
        }

        $table = (string) $table;

        if (str_contains($table, ' as ')) {
            [$base, $alias] = explode(' as ', $table, 2);

            return $this->wrapTable($base) . ' as ' . $this->wrapValue(trim($alias));
        }

        $table = $this->tablePrefix !== null && $this->tablePrefix !== '' && !str_starts_with($table, $this->tablePrefix)
            ? $this->tablePrefix . $table
            : $table;

        return $this->wrap($table);
    }

    public function parameter(mixed $value): string
    {
        return '?';
    }

    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        if (str_starts_with($value, $this->quoteCharacter) && str_ends_with($value, $this->quoteCharacter)) {
            return $value;
        }

        return $this->quoteCharacter . str_replace($this->quoteCharacter, $this->quoteCharacter . $this->quoteCharacter, $value) . $this->quoteCharacter;
    }

    protected function compileColumns(Builder $query): string
    {
        $columns = array_map(fn ($column) => $this->wrap($column), $query->columns);

        return 'select ' . ($query->distinct ? 'distinct ' : '') . ($columns === [] ? '*' : implode(', ', $columns));
    }

    protected function compileFrom(Builder $query): string
    {
        return 'from ' . $this->wrapTable($query->from);
    }

    protected function compileJoins(Builder $query): string
    {
        if ($query->joins === []) {
            return '';
        }

        $sql = [];

        foreach ($query->joins as $join) {
            $type = strtolower($join['type']);
            $table = $this->wrapTable($join['table']);
            $first = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second = $this->wrap($join['second']);

            $sql[] = "{$type} join {$table} on {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    protected function compileWheres(Builder $query): string
    {
        if ($query->wheres === []) {
            return '';
        }

        $sql = $this->compileWheresToArray($query, '');

        return 'where ' . implode(' ', $sql);
    }

    protected function compileWheresToArray(Builder $query, string $boolean): array
    {
        $sql = [];

        foreach ($query->wheres as $where) {
            $whereBoolean = $where['boolean'] ?? 'and';
            $prefix = $sql === [] ? '' : $whereBoolean . ' ';

            $method = 'where' . ucfirst($where['type']);

            if (!method_exists($this, $method)) {
                throw new RuntimeException("Unsupported where type: {$where['type']}");
            }

            $sql[] = $prefix . $this->{$method}($where);
        }

        return $sql;
    }

    protected function whereBasic(array $where): string
    {
        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $this->parameter($where['value']);
    }

    protected function whereIn(array $where): string
    {
        $values = implode(', ', array_fill(0, count($where['values']), '?'));

        return $this->wrap($where['column']) . ($where['not'] ? ' not' : '') . ' in (' . $values . ')';
    }

    protected function whereNull(array $where): string
    {
        return $this->wrap($where['column']) . ' is ' . ($where['not'] ? 'not ' : '') . 'null';
    }

    protected function whereBetween(array $where): string
    {
        return $this->wrap($where['column']) . ($where['not'] ? ' not' : '') . ' between ? and ?';
    }

    protected function whereNested(array $where): string
    {
        $nested = $where['query'];
        $sql = $this->compileWheres($nested);

        if (str_starts_with($sql, 'where ')) {
            $sql = substr($sql, 6);
        }

        return '(' . $sql . ')';
    }

    protected function whereRaw(array $where): string
    {
        return $where['sql'];
    }

    protected function compileGroups(Builder $query): string
    {
        if ($query->groups === []) {
            return '';
        }

        return 'group by ' . implode(', ', array_map([$this, 'wrap'], $query->groups));
    }

    protected function compileHavings(Builder $query): string
    {
        if ($query->havings === []) {
            return '';
        }

        $sql = [];

        foreach ($query->havings as $having) {
            $boolean = $having['boolean'] ?? 'and';
            $prefix = $sql === [] ? '' : $boolean . ' ';

            $sql[] = $prefix . $this->wrap($having['column']) . ' ' . $having['operator'] . ' ?';
        }

        return 'having ' . implode(' ', $sql);
    }

    protected function compileOrders(Builder $query): string
    {
        if ($query->orders === []) {
            return '';
        }

        $orders = [];

        foreach ($query->orders as $order) {
            $direction = strtolower($order['direction']) === 'asc' ? 'asc' : 'desc';
            $orders[] = $this->wrap($order['column']) . ' ' . $direction;
        }

        return 'order by ' . implode(', ', $orders);
    }

    protected function compileLimit(Builder $query): string
    {
        if ($query->limit === null) {
            return '';
        }

        return 'limit ' . $query->limit;
    }

    protected function compileOffset(Builder $query): string
    {
        if ($query->offset === null) {
            return '';
        }

        return 'offset ' . $query->offset;
    }

    public function compileCreate(Blueprint $blueprint): array
    {
        $columns = [];
        $statements = [];

        foreach ($blueprint->columns as $column) {
            $columns[] = $this->getColumnSql($column);
        }

        foreach ($blueprint->indexes as $index) {
            if ($index->type === 'index') {
                $statements[] = $this->compileIndex($index, $blueprint->table);

                continue;
            }

            $columns[] = $this->getIndexSql($index, $blueprint->table);
        }

        foreach ($blueprint->foreignKeys as $foreignKey) {
            if ($foreignKey->on === '') {
                continue;
            }

            $columns[] = $this->getForeignKeySql($foreignKey);
        }

        $sql = $blueprint->temporary ? 'create temporary table ' : 'create table ';
        $sql .= $this->wrapTable($blueprint->table) . ' (' . implode(', ', $columns) . ')';

        if ($this->driver === 'mysql') {
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
        }

        array_unshift($statements, $sql);

        return $statements;
    }

    public function compileIndex(Index $index, string $table): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $index->columns));

        return 'create index ' . $this->wrap($index->name) . ' on ' . $this->wrapTable($table) . ' (' . $columns . ')';
    }

    public function compileDrop(string $table): string
    {
        return 'drop table ' . $this->wrapTable($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return match ($this->driver) {
            'sqlite' => 'drop table if exists ' . $this->wrapTable($table),
            default => 'drop table if exists ' . $this->wrapTable($table),
        };
    }

    public function compileHasTable(string $table): string
    {
        return match ($this->driver) {
            'mysql' => 'show tables like ' . $this->quoteString($table),
            'sqlite' => "select name from sqlite_master where type = 'table' and name = " . $this->quoteString($table),
            'pgsql' => "select * from information_schema.tables where table_schema = 'public' and table_name = " . $this->quoteString($table),
            default => 'show tables like ' . $this->quoteString($table),
        };
    }

    public function compileHasColumn(string $table, string $column): string
    {
        return match ($this->driver) {
            'mysql' => 'show columns from ' . $this->wrapTable($table) . ' where Field = ' . $this->quoteString($column),
            'sqlite' => 'pragma table_info(' . $this->wrapTable($table) . ')',
            'pgsql' => "select * from information_schema.columns where table_name = " . $this->quoteString($table) . " and column_name = " . $this->quoteString($column),
            default => 'show columns from ' . $this->wrapTable($table) . ' where Field = ' . $this->quoteString($column),
        };
    }

    public function compileRename(string $from, string $to): string
    {
        return match ($this->driver) {
            'pgsql' => 'alter table ' . $this->wrapTable($from) . ' rename to ' . $this->wrapTable($to),
            default => 'rename table ' . $this->wrapTable($from) . ' to ' . $this->wrapTable($to),
        };
    }

    public function compileGetTables(): string
    {
        return match ($this->driver) {
            'mysql' => 'show tables',
            'sqlite' => "select name as name from sqlite_master where type = 'table' and name not like 'sqlite_%'",
            'pgsql' => "select table_name as name from information_schema.tables where table_schema = 'public'",
            default => 'show tables',
        };
    }

    public function compileTruncate(string $table): string
    {
        return match ($this->driver) {
            'sqlite' => 'delete from ' . $this->wrapTable($table),
            default => 'truncate table ' . $this->wrapTable($table),
        };
    }

    protected function getColumnSql(Column $column): string
    {
        return match ($this->driver) {
            'sqlite' => $this->getSqliteColumnSql($column),
            'pgsql' => $this->getPostgresColumnSql($column),
            default => $this->getMysqlColumnSql($column),
        };
    }

    protected function getIndexSql(Index $index, string $table): string
    {
        if ($index->type === 'dropIndex') {
            return match ($this->driver) {
                'sqlite' => 'drop index if exists ' . $this->wrap($index->name),
                default => 'drop index ' . $this->wrap($index->name) . ' on ' . $this->wrapTable($table),
            };
        }

        if ($index->type === 'dropUnique') {
            return match ($this->driver) {
                'sqlite' => 'drop index if exists ' . $this->wrap($index->name),
                default => 'drop index ' . $this->wrap($index->name) . ' on ' . $this->wrapTable($table),
            };
        }

        if ($index->type === 'dropPrimary') {
            return 'alter table ' . $this->wrapTable($table) . ' drop primary key';
        }

        $columns = implode(', ', array_map([$this, 'wrap'], $index->columns));

        return match ($index->type) {
            'primary' => 'primary key (' . $columns . ')',
            'unique' => 'constraint ' . $this->wrap($index->name) . ' unique (' . $columns . ')',
            default => 'index ' . $this->wrap($index->name) . ' (' . $columns . ')',
        };
    }

    protected function getForeignKeySql(ForeignKey $foreignKey): string
    {
        $columns = implode(', ', array_map([$this, 'wrap'], $foreignKey->columns));
        $references = implode(', ', array_map([$this, 'wrap'], $foreignKey->references));

        $sql = 'constraint ' . $this->wrap($foreignKey->name) . ' foreign key (' . $columns . ') references ' . $this->wrapTable($foreignKey->on) . ' (' . $references . ')';

        if ($foreignKey->onDelete !== null) {
            $sql .= ' on delete ' . $foreignKey->onDelete;
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= ' on update ' . $foreignKey->onUpdate;
        }

        return $sql;
    }

    protected function getMysqlColumnSql(Column $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getMysqlType($column);

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

    protected function getSqliteColumnSql(Column $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getSqliteType($column);

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

    protected function getPostgresColumnSql(Column $column): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->getPostgresType($column);

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

    protected function getMysqlType(Column $column): string
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
            'enum' => 'enum(' . implode(', ', array_map(fn ($v) => "'" . addslashes($v) . "'", $column->allowed ?? [])) . ')',
            'decimal' => 'decimal(' . ($column->total ?? 8) . ', ' . ($column->places ?? 2) . ')',
            'float' => 'float',
            'double' => 'double',
            'binary' => 'blob',
            default => 'varchar(255)',
        };
    }

    protected function getSqliteType(Column $column): string
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

    protected function getPostgresType(Column $column): string
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

    protected function formatDefault(mixed $value): string
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

        return "'" . addslashes((string) $value) . "'";
    }

    protected function quoteString(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }
}
