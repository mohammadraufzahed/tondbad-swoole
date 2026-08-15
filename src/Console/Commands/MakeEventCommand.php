<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:event', 'Create a new event class.')]
class MakeEventCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/event.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Events/' . $name . '.php';
    }
}
