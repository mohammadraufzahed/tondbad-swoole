<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Core\Route\Route;

#[AsCommand('route:list', 'List all registered routes.')]
class RouteListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $route = $app->container->make(Route::class);
        $routes = $route->getRoutes();

        if (count($routes) === 0) {
            $output->writeln('No routes registered.');

            return 0;
        }

        $rows = [];

        foreach ($routes as [$method, $path, $handler]) {
            $handlerString = is_array($handler) ? implode('::', $handler) : 'Closure';
            $rows[] = [$method, $path, $handlerString];
        }

        $output->table(['Method', 'Path', 'Handler'], $rows);

        return 0;
    }
}
