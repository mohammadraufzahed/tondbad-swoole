<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Support\Hash\HashManager;

#[AsCommand('hash:check', 'Check a plain-text value against a hash.')]
class HashCheckCommand extends Command
{
    #[Argument('value', mode: InputArgument::REQUIRED, description: 'Plain text value')]
    public string $value;

    #[Argument('hash', mode: InputArgument::REQUIRED, description: 'Hash to check against')]
    public string $hash;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = app()?->container->make(HashManager::class);

        if (!$manager instanceof HashManager) {
            $output->error('HashManager is not available.');

            return 1;
        }

        $output->writeln($manager->check($this->value, $this->hash) ? 'true' : 'false');

        return 0;
    }
}
