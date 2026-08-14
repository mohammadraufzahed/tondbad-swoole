<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    /**
     * @param list<class-string> $middlewares
     * @param list<class-string> $guards
     */
    public function __construct(
        private readonly string $path = '',
        private readonly array $middlewares = [],
        private readonly array $guards = [],
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<class-string>
     */
    public function middlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @return list<class-string>
     */
    public function guards(): array
    {
        return $this->guards;
    }
}
