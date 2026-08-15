<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Criteria;

use TondbadSwoole\Database\Query\Expression;

class Criteria
{
    /**
     * @var list<array{field: string|Expression, operator: string, value: mixed, boolean: string}>
     */
    private array $wheres = [];

    /**
     * @var array<string, string>
     */
    private array $orderings = [];

    private ?int $firstResult = null;

    private ?int $maxResults = null;

    public static function create(): self
    {
        return new self();
    }

    /**
     * @param array{0: string|Expression, 1: string, 2: mixed} $restriction
     */
    public function add(array $restriction, string $boolean = 'and'): self
    {
        [$field, $operator, $value] = [...$restriction, null];

        return $this->where($field, $operator, $value, $boolean);
    }

    public function where(string|Expression $field, string $operator = '=', mixed $value = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function andWhere(string|Expression $field, string $operator = '=', mixed $value = null): self
    {
        return $this->where($field, $operator, $value, 'and');
    }

    public function orWhere(string|Expression $field, string $operator = '=', mixed $value = null): self
    {
        return $this->where($field, $operator, $value, 'or');
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->orderings[$field] = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function setFirstResult(int $firstResult): self
    {
        $this->firstResult = $firstResult;

        return $this;
    }

    public function setMaxResults(int $maxResults): self
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * @return list<array{field: string|Expression, operator: string, value: mixed, boolean: string}>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * @return array<string, string>
     */
    public function getOrderings(): array
    {
        return $this->orderings;
    }

    public function getFirstResult(): ?int
    {
        return $this->firstResult;
    }

    public function getMaxResults(): ?int
    {
        return $this->maxResults;
    }
}
