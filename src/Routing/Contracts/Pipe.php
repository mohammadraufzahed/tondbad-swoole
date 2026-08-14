<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Contracts;

use ReflectionNamedType;
use ReflectionUnionType;

interface Pipe
{
    /**
     * @param ReflectionNamedType|ReflectionUnionType|null $type
     */
    public function transform(mixed $value, \ReflectionType|null $type = null): mixed;
}
