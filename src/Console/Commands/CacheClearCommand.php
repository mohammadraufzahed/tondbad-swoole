<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

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
        $this->deleteFiles($this->basePath . '/storage/cache/routes.cache.php');

        foreach (glob($this->basePath . '/storage/framework/*') ?: [] as $file) {
            if (is_file($file)) {
                $this->deleteFiles($file);
            }
        }

        fwrite(STDOUT, "Caches cleared.\n");

        return 0;
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
