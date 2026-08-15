<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('serve', 'Start the HTTP server.', coroutine: false)]
class ServeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app instanceof App && $app->config->get('app.type') === 'http') {
            $app->run();

            return 0;
        }

        AppFactory::create($this->basePath)->run();

        return 0;
    }
}
