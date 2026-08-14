<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

class GroupBuilder
{
    private string $prefix = '';

    /**
     * @var list<class-string>
     */
    private array $middlewares = [];

    private string $namePrefix = '';

    private string $namespace = '';

    /**
     * @var array<string, string>
     */
    private array $patterns = [];

    public function __construct(private readonly Route $route)
    {
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param list<class-string> $middlewares
     */
    public function middleware(array $middlewares): self
    {
        $this->middlewares = $middlewares;

        return $this;
    }

    public function name(string $prefix): self
    {
        $this->namePrefix = $prefix;

        return $this;
    }

    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function where(string $parameter, string $pattern): self
    {
        $this->patterns[$parameter] = $pattern;

        return $this;
    }

    public function whereNumber(string ...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9]+');
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

    /**
     * @param callable(Route): void $callback
     */
    public function group(callable $callback): void
    {
        $this->route->pushGroup(
            $this->prefix,
            $this->middlewares,
            $this->namePrefix,
            $this->namespace,
            $this->patterns,
        );

        $callback($this->route);

        $this->route->popGroup();
    }
}
