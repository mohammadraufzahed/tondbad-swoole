<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\View\ViewManager;

#[AsCommand('view:clear', 'Remove all compiled view templates.', coroutine: false)]
class ViewClearCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = app()?->container->make(ViewManager::class);

        if (!$manager instanceof ViewManager) {
            $output->error('View manager not available.');

            return 1;
        }

        $manager->clearCompiled();

        $output->success('Compiled views cleared.');

        return 0;
    }
}
