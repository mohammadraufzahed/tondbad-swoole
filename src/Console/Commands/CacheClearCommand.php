<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Bootstrap\App;

class CacheClearCommand extends Command
{
    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Clear compiled route and framework caches.';
    }

    public function run(array $args): int
    {
        [$routeCacheFile, $frameworkDir] = $this->cachePaths();

        $this->deleteFiles($routeCacheFile);

        foreach (glob($frameworkDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $this->deleteFiles($file);
            }
        }

        fwrite(STDOUT, "Caches cleared.\n");

        return 0;
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
