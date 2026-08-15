<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use TondbadSwoole\Bootstrap\App;

class CacheClearCommand extends Command
{
    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Clear the framework and data caches.';
    }

    public function run(array $args): int
    {
        $this->clearDataCache();

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

        fwrite(STDOUT, "Caches cleared.\n");

        return 0;
    }

    private function clearDataCache(): void
    {
        $cache = cache();

        if ($cache === null) {
            return;
        }

        $this->runInCoroutine(fn () => $cache->clear());
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

    /**
     * @return array{0: string, 1: string}
     */
    private function cachePaths(): array
    {
        $app = app();

        if ($app instanceof App) {
            $basePath = $app->basePath();
            $routeCacheFile = $app->config->get('app.route_cache_file', $basePath . '/storage/cache/routes.cache.php');
            $frameworkDir = $app->config->get('app.framework_cache_dir', $basePath . '/storage/framework');

            return [$routeCacheFile, $frameworkDir];
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
