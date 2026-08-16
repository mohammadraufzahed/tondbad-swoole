<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\View\ViewManager;

#[AsCommand('view:cache', 'Pre-compile all .tond.php view templates.', coroutine: false)]
class ViewCacheCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = app()?->container->make(ViewManager::class);

        if (!$manager instanceof ViewManager) {
            $output->error('View manager not available.');

            return 1;
        }

        $manager->compileAll();

        $output->success('View templates cached.');

        return 0;
    }
}
