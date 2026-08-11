<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Query\Grammars;

use TondbadSwoole\Database\Query\Grammar;

class PostgresGrammar extends Grammar
{
    protected string $quoteCharacter = '"';
}
