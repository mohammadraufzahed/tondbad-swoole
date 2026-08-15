<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:listener', 'Create a new event listener class.')]
class MakeListenerCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/listener.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Listeners/' . $name . '.php';
    }
}
