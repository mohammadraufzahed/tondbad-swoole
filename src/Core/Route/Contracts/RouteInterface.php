<?php

namespace TondbadSwoole\Core\Route\Contracts;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

interface RouteInterface
{
    public static function addRoute(string $method, string $path, callable $handler): void;

    public static function registerAnnotatedRoutes(array $classNames): void;

    public function dispatch(Request $request, Response $response): void;
}