<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Bootstrap\AppFactory;

class GrpcServeCommand extends Command
{
    public function getName(): string
    {
        return 'serve:grpc';
    }

    public function getDescription(): string
    {
        return 'Start the gRPC server.';
    }

    public function run(array $args): int
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
