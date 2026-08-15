<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('serve:grpc', 'Start the gRPC server.', coroutine: false)]
class GrpcServeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app instanceof App && $app->config->get('app.type') === 'grpc') {
            $app->run();

            return 0;
        }

        $_ENV['APP_TYPE'] = 'grpc';
        $_SERVER['APP_TYPE'] = 'grpc';

        AppFactory::create($this->basePath)->run();

        return 0;
    }
}
