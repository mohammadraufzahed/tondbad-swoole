<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use InvalidArgumentException;

class RouteDefinition
{
    public function __construct(
        private readonly Route $route,
        private readonly int $id,
    ) {
    }

    public function where(string $parameter, string $pattern): self
    {
        $this->route->setConstraint($this->id, $parameter, $pattern);

        return $this;
    }

    public function whereNumber(string ...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9]+');
        }

        return $this;
    }

    public function whereAlpha(string ...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z]+');
        }

        return $this;
    }

    public function whereAlphaNumeric(string ...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z0-9_-]+');
        }

        return $this;
    }

    public function whereUuid(string ...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
        }

        return $this;
    }

    public function name(string $name): self
    {
        $this->route->rename($this->id, $name);

        return $this;
    }

    public function middleware(array $middlewares): self
    {
        $this->route->setMiddleware($this->id, $middlewares);

        return $this;
    }
}
