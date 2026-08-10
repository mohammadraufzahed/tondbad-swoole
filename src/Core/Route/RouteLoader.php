<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use InvalidArgumentException;

class RouteLoader
{
    public function load(string $path, Route $route): void
    {
        if (!file_exists($path)) {
            return;
        }

        $callback = require $path;

        if (!is_callable($callback)) {
            throw new InvalidArgumentException("Route file [{$path}] must return a callable.");
        }

        $callback($route);
    }
}
