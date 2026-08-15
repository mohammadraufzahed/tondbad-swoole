<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('cache:clear', 'Clear the framework and data caches.', coroutine: false)]
class CacheClearCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = cache();

        if ($cache !== null) {
            $cache->clear();
        }

        [$routeCacheFile, $frameworkDir] = $this->cachePaths();

        if (is_string($routeCacheFile) && $routeCacheFile !== '') {
            $this->deleteFiles($routeCacheFile);
        }

        if (is_string($frameworkDir) && $frameworkDir !== '') {
            foreach (glob($frameworkDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    $this->deleteFiles($file);
                }
            }
        }

        $output->success('Caches cleared.');

        return 0;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function cachePaths(): array
    {
        $app = app();

        if ($app instanceof App) {
            $basePath = $app->basePath();

            return [
                $app->config->get('app.route_cache_file', $basePath . '/storage/cache/routes.cache.php'),
                $app->config->get('app.framework_cache_dir', $basePath . '/storage/framework'),
            ];
        }

        return [
            $this->basePath . '/storage/cache/routes.cache.php',
            $this->basePath . '/storage/framework',
        ];
    }

    private function deleteFiles(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
