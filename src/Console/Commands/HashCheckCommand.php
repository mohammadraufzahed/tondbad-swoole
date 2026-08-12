<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class HashCheckCommand extends Command
{
    public function getName(): string
    {
        return 'hash:check';
    }

    public function getDescription(): string
    {
        return 'Check a plain-text value against a hash.';
    }

    public function run(array $args): int
    {
        $value = $args[0] ?? null;
        $hash = $args[1] ?? null;

        if ($value === null || $value === '' || $hash === null || $hash === '') {
            fwrite(STDERR, "Usage: tondbad {$this->getName()} <value> <hash>\n");

            return 1;
        }

        fwrite(STDOUT, (hash()->check($value, $hash) ? 'true' : 'false') . PHP_EOL);

        return 0;
    }
}
