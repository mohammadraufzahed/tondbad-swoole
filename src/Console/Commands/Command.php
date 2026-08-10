<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\CommandInterface;

abstract class Command implements CommandInterface
{
    public function __construct(protected readonly string $basePath)
    {
    }

    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    protected function writeFile(string $path, string $content): bool
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (file_exists($path)) {
            fwrite(STDERR, "File already exists: {$path}\n");

            return false;
        }

        file_put_contents($path, $content);

        return true;
    }
}
