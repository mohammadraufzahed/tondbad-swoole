<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Operations;

class MySqlOperations extends AbstractOperations
{
    public function getQuoteCharacter(): string
    {
        return '`';
    }
}
