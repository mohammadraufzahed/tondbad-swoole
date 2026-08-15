<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query\Grammars;

use TondbadSwoole\Database\Features\PostgresFeatures;
use TondbadSwoole\Database\Operations\PostgresOperations;
use TondbadSwoole\Database\Query\Grammar;

class PostgresGrammar extends Grammar
{
    public function __construct()
    {
        parent::__construct(new PostgresOperations(), new PostgresFeatures());
    }

    public function jsonContainsBindings(string $path, mixed $value): array
    {
        return [$value];
    }

    protected function whereJsonContains(array $where): string
    {
        $column = $this->wrap($where['column']) . '::jsonb';
        $path = $this->compileJsonPath($where['path']);

        if ($path !== '') {
            $column = 'jsonb_extract_path(' . $column . $path . ')';
        }

        return ($where['not'] ? 'not ' : '') . '(' . $column . ' @> jsonb_build_array(?))';
    }

    public function jsonLengthBindings(string $path, mixed $value): array
    {
        return [$value];
    }

    protected function whereJsonLength(array $where): string
    {
        $column = $this->wrap($where['column']) . '::jsonb';
        $path = $this->compileJsonPath($where['path']);

        if ($path !== '') {
            $column = 'jsonb_array_length(jsonb_extract_path(' . $column . $path . '))';
        } else {
            $column = 'jsonb_array_length(' . $column . ')';
        }

        return $column . ' ' . $where['operator'] . ' cast(? as integer)';
    }

    protected function compileJsonPath(string $path): string
    {
        $path = ltrim($path, '$.');

        if ($path === '' || $path === '$') {
            return '';
        }

        $parts = array_map(
            fn ($part) => $this->quoteString($part),
            explode('.', $path)
        );

        return ', ' . implode(', ', $parts);
    }
}
