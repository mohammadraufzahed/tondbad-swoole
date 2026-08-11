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

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function addSelect(array|string $column): self
    {
        $columns = is_array($column) ? $column : func_get_args();
        $this->columns = array_merge($this->columns, $columns);

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function where(string|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
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

    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): self
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

    public function having(string $column, mixed $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function first(): ?array
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    public function find(mixed $id, array|string $columns = ['*']): ?array
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
        $this->insert($values);

        return $this->connection->lastInsertId($sequence);
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
