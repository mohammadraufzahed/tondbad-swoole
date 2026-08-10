<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\AppFactory;

class ServeCommand extends Command
{
    public function getName(): string
    {
        return 'serve';
    }

    public function getDescription(): string
    {
        return 'Start the HTTP server.';
    }

    public function run(array $args): int
    {
        AppFactory::create($this->basePath)->run();

        return 0;
    }
}
