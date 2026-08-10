<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\AppFactory;

class RouteCacheCommand extends Command
{
    public function getName(): string
    {
        return 'route:cache';
    }

    public function getDescription(): string
    {
        return 'Pre-compile the route cache.';
    }

    public function run(array $args): int
    {
        $app = AppFactory::create($this->basePath);
        $app->routes()->warmRouteCache();

        fwrite(STDOUT, "Route cache compiled.\n");

        return 0;
    }
}
