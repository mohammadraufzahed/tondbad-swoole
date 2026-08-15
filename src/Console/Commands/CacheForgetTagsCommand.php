<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('cache:forget-tags', 'Invalidate all cache entries associated with one or more tags.', coroutine: false)]
class CacheForgetTagsCommand extends Command
{
    #[Argument('tags', mode: InputArgument::REQUIRED | InputArgument::IS_ARRAY, description: 'Tags to invalidate')]
    public array $tags = [];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = cache();

        if ($cache === null) {
            $output->error('Cache is not available.');

            return 1;
        }

        if (!$cache->invalidateTags($this->tags)) {
            $output->error('Failed to invalidate tags.');

            return 1;
        }

        $output->success('Forgot tags: ' . implode(', ', $this->tags));

        return 0;
    }
}
