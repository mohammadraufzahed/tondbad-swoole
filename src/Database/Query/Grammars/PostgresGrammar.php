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
}
