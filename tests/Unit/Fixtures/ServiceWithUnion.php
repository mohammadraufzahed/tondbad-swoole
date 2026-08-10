<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

class ServiceWithUnion
{
    public Logger $logger;

    public function __construct(Unresolvable|Logger $logger)
    {
        $this->logger = $logger;
    }
}
