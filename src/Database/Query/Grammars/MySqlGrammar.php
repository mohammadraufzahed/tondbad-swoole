<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query\Grammars;

use TondbadSwoole\Database\Features\MySqlFeatures;
use TondbadSwoole\Database\Operations\MySqlOperations;
use TondbadSwoole\Database\Query\Grammar;

class MySqlGrammar extends Grammar
{
    public function __construct()
    {
        parent::__construct(new MySqlOperations(), new MySqlFeatures());
    }

    public function jsonContainsBindings(string $path, mixed $value): array
    {
        return [$value, $path];
    }

    protected function whereJsonContains(array $where): string
    {
        return ($where['not'] ? 'not ' : '') . 'json_contains(' . $this->wrap($where['column']) . ', cast(? as json), ?)';
    }

    public function jsonLengthBindings(string $path, mixed $value): array
    {
        return [$path, $value];
    }

    protected function whereJsonLength(array $where): string
    {
        return 'json_length(' . $this->wrap($where['column']) . ', ?) ' . $where['operator'] . ' cast(? as unsigned)';
    }
}
