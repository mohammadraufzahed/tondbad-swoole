<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

class MigrationPathManager
{
    /** @var list<string> */
    private array $paths = [];

    public function addPath(string $path, bool $prepend = false): self
    {
        $path = rtrim($path, '/');

        if ($prepend) {
            array_unshift($this->paths, $path);
        } else {
            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getDefaultPath(): string
    {
        return $this->paths[0] ?? '';
    }

    public function getFullPath(string $file): ?string
    {
        foreach ($this->paths as $path) {
            $candidate = $path . '/' . ltrim($file, '/');

            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Return a sorted list of migration file basenames found across all registered paths.
     *
     * @return list<string>
     */
    public function getFiles(): array
    {
        $files = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $matches = glob($path . '/*_*.php');

            if ($matches === false) {
                continue;
            }

            foreach ($matches as $file) {
                $name = basename($file);
                $files[$name] = $name;
            }
        }

        sort($files);

        return array_values($files);
    }
}
