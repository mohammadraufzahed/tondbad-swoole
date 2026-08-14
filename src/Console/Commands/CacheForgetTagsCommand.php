<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;

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
        if ($args === []) {
            fwrite(STDERR, "Usage: cache:forget-tags {tag1} [{tag2} ...]\n");

            return 1;
        }

        $cache = cache();

        if ($cache === null) {
            fwrite(STDERR, "Cache is not available.\n");

            return 1;
        }

        $success = $this->runInCoroutine(fn () => $cache->invalidateTags($args));

        if (!$success) {
            fwrite(STDERR, "Failed to invalidate tags.\n");

            return 1;
        }

        fwrite(STDOUT, "Forgot tags: " . implode(', ', $args) . "\n");

        return 0;
    }

    private function runInCoroutine(callable $callback): mixed
    {
        if (Coroutine::getCid() !== -1) {
            return $callback();
        }

        Runtime::enableCoroutine(SWOOLE_HOOK_TCP);

        $result = null;
        Coroutine::run(function () use ($callback, &$result): void {
            $result = $callback();
        });

        return $result;
    }
}
