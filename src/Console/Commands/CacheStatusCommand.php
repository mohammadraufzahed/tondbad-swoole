<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class CacheStatusCommand extends Command
{
    public function getName(): string
    {
        return 'cache:status';
    }

    public function getDescription(): string
    {
        return 'Show cache statistics.';
    }

    public function run(array $args): int
    {
        $cache = cache();

        if ($cache === null) {
            fwrite(STDERR, "Cache is not available.\n");

            return 1;
        }

        $stats = $cache->stats();

        fwrite(STDOUT, json_encode($stats, JSON_PRETTY_PRINT) . "\n");

        return 0;
    }
}
