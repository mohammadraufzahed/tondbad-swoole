<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Support\Hash\HashManager;

class HashMakeCommand extends Command
{
    public function getName(): string
    {
        return 'hash:make';
    }

    public function getDescription(): string
    {
        return 'Hash a plain-text value.';
    }

    public function run(array $args): int
    {
        $value = $args[0] ?? null;

        if ($value === null || $value === '') {
            fwrite(STDERR, "Usage: tondbad {$this->getName()} <value>\n");

            return 1;
        }

        $manager = app()?->container->make(HashManager::class);

        if (!$manager instanceof HashManager) {
            fwrite(STDERR, "HashManager is not available.\n");

            return 1;
        }

        fwrite(STDOUT, $manager->make($value) . PHP_EOL);

        return 0;
    }
}
