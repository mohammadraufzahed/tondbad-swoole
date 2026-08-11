<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

interface PoolInterface
{
    public function get(): mixed;

    public function put(mixed $resource): void;
}
