<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query\Grammars;

use TondbadSwoole\Database\Query\Grammar;

class MySqlGrammar extends Grammar
{
    protected string $quoteCharacter = '`';
}
