<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query\Grammars;

use TondbadSwoole\Database\Features\SqliteFeatures;
use TondbadSwoole\Database\Operations\SqliteOperations;
use TondbadSwoole\Database\Query\Grammar;

class SqliteGrammar extends Grammar
{
    public function __construct()
    {
        parent::__construct(new SqliteOperations(), new SqliteFeatures());
    }
}
