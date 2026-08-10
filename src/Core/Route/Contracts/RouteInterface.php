<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route\Contracts;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

interface RouteInterface
{
    /**
     * @param array<class-string> $classNames
     */
    public function registerAnnotatedRoutes(array $classNames): void;

    public function addRoute(
        string $method,
        string $path,
        array|callable $handler,
        array $middlewares = [],
        ?string $name = null
    ): void;

    public function dispatch(Request $request, Response $response): void;
}
