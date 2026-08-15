<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('cache:status', 'Show cache statistics.', coroutine: false)]
class CacheStatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = cache();

        if ($cache === null) {
            $output->error('Cache is not available.');

            return 1;
        }

        $output->writeln(json_encode($cache->stats(), JSON_PRETTY_PRINT));

        return 0;
    }
}
