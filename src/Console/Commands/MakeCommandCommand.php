<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:command', 'Create a new console command class.')]
class MakeCommandCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['Command'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/command.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Console/Commands/' . $name . 'Command.php';
    }
}
