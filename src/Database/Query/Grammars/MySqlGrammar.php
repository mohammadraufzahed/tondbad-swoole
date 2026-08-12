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
}
