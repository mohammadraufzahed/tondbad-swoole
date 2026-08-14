<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use InvalidArgumentException;
use RuntimeException;

abstract class MakeCommand extends Command
{
    public function run(array $args): int
    {
        if (empty($args)) {
            fwrite(STDERR, "Usage: tondbad {$this->getName()} <name>\n");

            return 1;
        }

        $name = $this->normalizeName($args[0]);
        $path = $this->getDefaultPath($name);

        $this->ensureDirectory(dirname($path));

        if (file_exists($path)) {
            fwrite(STDERR, "File already exists: {$path}\n");

            return 1;
        }

        file_put_contents($path, $this->compileStub($name));

        fwrite(STDOUT, "Created: {$path}\n");

        return 0;
    }

    protected function getNameSuffixes(): array
    {
        return [];
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace(['/', '\\'], '/', $name);
        $name = basename($name, '.php');
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $name = ucfirst($name);

        if ($name === '') {
            throw new InvalidArgumentException('Invalid class name provided.');
        }

        foreach ($this->getNameSuffixes() as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $name = substr($name, 0, -strlen($suffix));

                break;
            }
        }

        if ($name === '') {
            throw new InvalidArgumentException('Invalid class name provided.');
        }

        return $name;
    }

    private function compileStub(string $name): string
    {
        $stubPath = $this->getStubPath();
        $content = file_get_contents($stubPath);

        if ($content === false) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $replacements = [
            '{Name}' => $name,
            '{Slug}' => lcfirst($name),
            '{slug}' => strtolower($name),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    abstract protected function getStubPath(): string;

    abstract protected function getDefaultPath(string $name): string;
}
