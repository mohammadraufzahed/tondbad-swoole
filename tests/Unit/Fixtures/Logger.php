<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

class Logger
{
    public string $name;

    public function __construct(string $name = 'default')
    {
        $this->name = $name;
    }
}
