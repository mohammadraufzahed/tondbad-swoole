<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query;

use Closure;
use InvalidArgumentException;
use RuntimeException;

abstract class Grammar
{
    protected string $quoteCharacter = '"';

    protected ?string $tablePrefix = null;

    public function setTablePrefix(?string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    public function getTablePrefix(): ?string
    {
        return $this->tablePrefix;
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
}
