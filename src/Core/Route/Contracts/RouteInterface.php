<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route\Contracts;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Core\Route\RouteDefinition;

interface RouteInterface
{
    /**
     * @param array<class-string> $classNames
     */
    public function registerAnnotatedRoutes(array $classNames): void;

    /**
     * @param string|list<string> $method
     * @param list<class-string> $middlewares
     */
    public function addRoute(
        string|array $method,
        string $path,
        array|callable $handler,
        array $middlewares = [],
        ?string $name = null
    ): RouteDefinition;

    public function dispatch(Request $request, Response $response): void;
}
