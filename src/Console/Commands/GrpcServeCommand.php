<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

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
        $_ENV['APP_TYPE'] = 'grpc';

        AppFactory::create($this->basePath)->run();

        return 0;
    }
}
