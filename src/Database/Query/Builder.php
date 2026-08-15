<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query;

use Closure;
use InvalidArgumentException;
use TondbadSwoole\Database\ConnectionInterface;

class Builder
{
    public ?string $from = null;

    public array $columns = ['*'];

    public bool $distinct = false;

    public array $wheres = [];

    public array $joins = [];

    public array $groups = [];

    public array $havings = [];

    public array $orders = [];

    public ?int $limit = null;

    public ?int $offset = null;

    public bool $lockForUpdate = false;

    public bool $skipLocked = false;

    public array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    public function __construct(
        protected ConnectionInterface $connection,
        protected Grammar $grammar,
    ) {
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    public function newQuery(): self
    {
        return new self($this->connection, $this->grammar);
    }

    public function table(string $table, ?string $as = null): self
    {
        $this->from = $as !== null ? "{$table} as {$as}" : $table;

        return $this;
    }

    public function from(string $table, ?string $as = null): self
    {
        return $this->table($table, $as);
    }

    public function select(array|string|Expression|self $columns = ['*']): self
    {
        $this->columns = [];
        $this->bindings['select'] = [];

        if (!is_array($columns)) {
            $columns = func_get_args();
        }

        foreach ($columns as $key => $column) {
            $this->addSelectColumn($column, is_string($key) ? $key : null);
        }

        return $this;
    }

    public function addSelect(array|string|Expression|self $column): self
    {
        $columns = is_array($column) ? $column : func_get_args();

        foreach ($columns as $key => $value) {
            $this->addSelectColumn($value, is_string($key) ? $key : null);
        }

        return $this;
    }

    protected function addSelectColumn(mixed $column, ?string $alias = null): void
    {
        if ($column instanceof self) {
            $this->addFullSubQueryBindings($column, 'select');
        }

        if ($alias !== null) {
            $this->columns[$alias] = $column;

            return;
        }

        $this->columns[] = $column;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function lockForUpdate(): self
    {
        $this->lockForUpdate = true;

        return $this;
    }

    public function skipLocked(): self
    {
        $this->skipLocked = true;

        return $this;
    }

    public function where(string|array|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (is_array($column)) {
            if ($operator === null && $value === null) {
                foreach ($column as $key => $val) {
                    $this->where($key, '=', $val, $boolean);
                }

                return $this;
            }

            foreach ($column as $index => $col) {
                if (is_array($value) && array_key_exists($col, $value)) {
                    $val = $value[$col];
                } else {
                    $val = is_array($value) ? ($value[$index] ?? null) : $value;
                }

                $this->where($col, $operator, $val, $boolean);
            }

            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value instanceof Closure || $value instanceof self) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function orWhere(string|array|Closure $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not,
        ];

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'Between',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not,
        ];

        $this->addBinding($values[0] ?? null, 'where');
        $this->addBinding($values[1] ?? null, 'where');

        return $this;
    }

    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'or');
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function orWhereNotBetween(string $column, array $values): self
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    public function whereSub(string $column, string $operator, self|Closure $query, string $boolean = 'and'): self
    {
        $query = $this->resolveSubQuery($query);

        $this->addFullSubQueryBindings($query, 'where');

        $this->wheres[] = [
            'type' => 'Sub',
            'column' => $column,
            'operator' => $operator,
            'query' => $query,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereSub(string $column, string $operator, self|Closure $query): self
    {
        return $this->whereSub($column, $operator, $query, 'or');
    }

    public function whereExists(self|Closure $callback, string $boolean = 'and', bool $not = false): self
    {
        $query = $this->resolveSubQuery($callback);

        $this->addFullSubQueryBindings($query, 'where');

        $this->wheres[] = [
            'type' => 'Exists',
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    public function orWhereExists(self|Closure $callback): self
    {
        return $this->whereExists($callback, 'or');
    }

    public function whereNotExists(self|Closure $callback, string $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    public function orWhereNotExists(self|Closure $callback): self
    {
        return $this->whereExists($callback, 'or', true);
    }

    public function whereColumn(string|array $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): self
    {
        if (is_array($first)) {
            foreach ($first as $clause) {
                $this->whereColumn($clause[0], $clause[1], $clause[2], $boolean);
            }

            return $this;
        }

        if ($operator !== null && $second === null) {
            $second = $operator;
            $operator = '=';
        }

        if ($operator === null || $second === null) {
            throw new InvalidArgumentException('whereColumn requires two operands and an operator.');
        }

        $this->wheres[] = [
            'type' => 'Column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereColumn(string|array $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    public function whereJsonContains(string $column, mixed $value, string $boolean = 'and', bool $not = false): self
    {
        [$col, $path] = $this->parseJsonColumnAndPath($column);

        foreach ($this->grammar->jsonContainsBindings($path, $value) as $binding) {
            $this->addBinding($binding, 'where');
        }

        $this->wheres[] = [
            'type' => 'JsonContains',
            'column' => $col,
            'path' => $path,
            'value' => $value,
            'boolean' => $boolean,
            'not' => $not,
        ];

        return $this;
    }

    public function orWhereJsonContains(string $column, mixed $value): self
    {
        return $this->whereJsonContains($column, $value, 'or');
    }

    public function whereJsonDoesntContain(string $column, mixed $value, string $boolean = 'and'): self
    {
        return $this->whereJsonContains($column, $value, $boolean, true);
    }

    public function whereJsonLength(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        [$col, $path] = $this->parseJsonColumnAndPath($column);

        if ($operator !== null && $value === null && !is_numeric($operator)) {
            $value = $operator;
            $operator = '=';
        }

        foreach ($this->grammar->jsonLengthBindings($path, $value) as $binding) {
            $this->addBinding($binding, 'where');
        }

        $this->wheres[] = [
            'type' => 'JsonLength',
            'column' => $col,
            'path' => $path,
            'operator' => $operator ?? '=',
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereJsonLength(string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->whereJsonLength($column, $operator, $value, 'or');
    }

    public function whereAny(array $columns, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'Any',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        foreach ($columns as $_) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function whereAll(array $columns, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'All',
            'columns' => $columns,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        foreach ($columns as $_) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function orWhereAny(array $columns, string $operator, mixed $value): self
    {
        return $this->whereAny($columns, $operator, $value, 'or');
    }

    public function orWhereAll(array $columns, string $operator, mixed $value): self
    {
        return $this->whereAll($columns, $operator, $value, 'or');
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'boolean' => $boolean,
        ];

        foreach ($bindings as $binding) {
            $this->addBinding($binding, 'where');
        }

        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator ?? '=',
            'second' => $second ?? $first,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function groupBy(array|string $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->groups = array_merge($this->groups, $columns);

        return $this;
    }

    public function having(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'having');

        return $this;
    }

    public function orHaving(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->having($column, $operator, $value, 'or');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderByDesc($column);
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): self
    {
        $this->limit = max($value, 0);

        return $this;
    }

    public function take(int $value): self
    {
        return $this->limit($value);
    }

    public function offset(int $value): self
    {
        $this->offset = max($value, 0);

        return $this;
    }

    public function skip(int $value): self
    {
        return $this->offset($value);
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function when(mixed $value, Closure $callback, ?Closure $default = null): self
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    public function raw(string $value): Expression
    {
        return new Expression($value);
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['select'],
            $this->bindings['from'],
            $this->bindings['join'],
            $this->bindings['where'],
            $this->bindings['having'],
            $this->bindings['order'],
            $this->bindings['union'],
        );
    }

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    public function first(): mixed
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function find(mixed $id, array|string $columns = ['*']): mixed
    {
        return $this->where('id', '=', $id)->select($columns)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->first();

        return $row !== null ? ($row[$column] ?? null) : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();
        $values = [];

        foreach ($results as $row) {
            $value = $row[$column] ?? null;

            if ($key !== null) {
                $values[$row[$key] ?? null] = $value;
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('count', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('max', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('min', $column);
    }

    public function sum(string $column): mixed
    {
        return $this->aggregate('sum', $column);
    }

    public function avg(string $column): mixed
    {
        return $this->aggregate('avg', $column);
    }

    public function aggregate(string $function, string $column): mixed
    {
        $this->columns = [$this->raw("{$function}({$this->wrapColumn($column)}) as aggregate")];

        $row = $this->first();

        return $row !== null ? ($row['aggregate'] ?? null) : null;
    }

    public function chunk(int $count, Closure $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $count)->get();

            $countResults = count($results);

            if ($countResults === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults === $count);

        return true;
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        $values = $this->prepareInsertForBindings($values);

        $sql = $this->grammar->compileInsert($this, $values);
        $bindings = $this->getInsertBindings($values);

        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        $values = $this->prepareInsertForBindings($values);

        $sql = $this->grammar->compileInsert($this, $values);
        $bindings = $this->getInsertBindings($values);

        return $this->connection->insertGetId($sql, $bindings, $sequence);
    }

    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this, $values);
        $bindings = array_merge(array_values($values), $this->getBindingsForUpdate());

        return $this->connection->update($sql, $bindings);
    }

    public function delete(mixed $id = null): int
    {
        if ($id !== null) {
            $this->where('id', '=', $id);
        }

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->getBindingsForDelete());
    }

    public function truncate(): void
    {
        $this->connection->statement('truncate table ' . $this->grammar->wrapTable($this->from));
    }

    protected function whereNested(Closure $callback, string $boolean): self
    {
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = [
            'type' => 'Nested',
            'query' => $query,
            'boolean' => $boolean,
        ];

        $this->addNestedBindings($query, 'where');

        return $this;
    }

    protected function addBinding(mixed $value, string $type = 'where'): void
    {
        $this->bindings[$type][] = $value;
    }

    protected function addNestedBindings(self $query, string $type): void
    {
        $this->bindings[$type] = array_merge($this->bindings[$type], $query->bindings[$type]);
    }

    protected function addFullSubQueryBindings(self $query, string $type): void
    {
        $this->bindings[$type] = array_merge($this->bindings[$type], $query->getBindings());
    }

    protected function resolveSubQuery(self|Closure $query): self
    {
        if ($query instanceof Closure) {
            $query = $query($this->newQuery());
        }

        if (!$query instanceof self) {
            throw new InvalidArgumentException('A subquery must return an instance of ' . self::class . '.');
        }

        return $query;
    }

    protected function parseJsonColumnAndPath(string $column): array
    {
        if (!str_contains($column, '->')) {
            return [$column, '$'];
        }

        $parts = explode('->', $column, 2);
        $path = '$.' . str_replace('->', '.', $parts[1]);

        return [$parts[0], $path];
    }

    protected function prepareInsertForBindings(array $values): array
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $first = reset($values);
        $columns = array_keys($first);

        return array_map(
            fn ($row) => array_merge(array_fill_keys($columns, null), $row),
            $values
        );
    }

    protected function getInsertBindings(array $values): array
    {
        $bindings = [];
        $columns = array_keys(reset($values));

        foreach ($values as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        return $bindings;
    }

    protected function getBindingsForUpdate(): array
    {
        return $this->bindings['where'];
    }

    protected function getBindingsForDelete(): array
    {
        return $this->bindings['where'];
    }

    protected function wrapColumn(string $column): string
    {
        return $this->grammar->wrap($column);
    }
}
