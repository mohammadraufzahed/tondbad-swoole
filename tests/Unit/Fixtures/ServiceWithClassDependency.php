<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

class ServiceWithClassDependency
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }
}
