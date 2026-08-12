<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Core\Route\Route;

class RouteListCommand extends Command
{
    public function getName(): string
    {
        return 'route:list';
    }

    public function getDescription(): string
    {
        return 'List all registered routes.';
    }

    public function run(array $args): int
    {
        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $route = $app->container->make(Route::class);
        $routes = $route->getRoutes();

        if (count($routes) === 0) {
            fwrite(STDOUT, "No routes registered.\n");

            return 0;
        }

        fwrite(STDOUT, sprintf("%-8s %-30s %s\n", 'Method', 'Path', 'Handler'));
        fwrite(STDOUT, str_repeat('-', 70) . "\n");

        foreach ($routes as [$method, $path, $handler]) {
            $handlerString = is_array($handler) ? implode('::', $handler) : 'Closure';

            fwrite(STDOUT, sprintf("%-8s %-30s %s\n", $method, $path, $handlerString));
        }

        return 0;
    }
}
