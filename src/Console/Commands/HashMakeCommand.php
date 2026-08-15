<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Support\Hash\HashManager;

#[AsCommand('hash:make', 'Hash a plain-text value.')]
class HashMakeCommand extends Command
{
    #[Argument('value', mode: InputArgument::REQUIRED, description: 'Value to hash')]
    public string $value;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = app()?->container->make(HashManager::class);

        if (!$manager instanceof HashManager) {
            $output->error('HashManager is not available.');

            return 1;
        }

        $output->writeln($manager->make($this->value));

        return 0;
    }
}
