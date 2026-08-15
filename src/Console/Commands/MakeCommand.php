<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use InvalidArgumentException;
use RuntimeException;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

abstract class MakeCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the class to generate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->normalizeName($input->getArgument('name'));
        $path = $this->getDefaultPath($name);

        $this->ensureDirectory(dirname($path));

        if (file_exists($path)) {
            $output->error("File already exists: {$path}");

            return 1;
        }

        file_put_contents($path, $this->compileStub($name));
        $output->success("Created: {$path}");

        return 0;
    }

    protected function getNameSuffixes(): array
    {
        return [];
    }

    abstract protected function getStubPath(): string;

    abstract protected function getDefaultPath(string $name): string;

    private function normalizeName(string $name): string
    {
        $name = str_replace(['/', '\\'], '/', $name);
        $name = basename($name, '.php');
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name);

        if ($name === '') {
            throw new InvalidArgumentException('Invalid class name provided.');
        }

        $name = ucfirst($name);

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
}
