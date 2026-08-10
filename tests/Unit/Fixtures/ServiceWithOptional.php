<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

class ServiceWithOptional
{
    public ?Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
}
