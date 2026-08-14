<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class CacheForgetTagsCommand extends Command
{
    public function getName(): string
    {
        return 'cache:forget-tags';
    }

    public function getDescription(): string
    {
        return 'Invalidate all cache entries associated with one or more tags.';
    }

    public function run(array $args): int
    {
        $tags = array_slice($args, 1);

        if ($tags === []) {
            fwrite(STDERR, "Usage: cache:forget-tags {tag1} [{tag2} ...]\n");

            return 1;
        }

        $cache = cache();

        if ($cache === null) {
            fwrite(STDERR, "Cache is not available.\n");

            return 1;
        }

        $cache->invalidateTags($tags);

        fwrite(STDOUT, "Forgot tags: " . implode(', ', $tags) . "\n");

        return 0;
    }
}
