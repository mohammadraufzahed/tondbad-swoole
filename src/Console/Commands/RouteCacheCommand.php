<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('route:cache', 'Pre-compile the route cache.')]
class RouteCacheCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if (!$app instanceof App) {
            $app = AppFactory::create($this->basePath);
        }

        $app->routes()->warmRouteCache();

        $output->success('Route cache compiled.');

        return 0;
    }
}
