<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeEventCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:event';
    }

    public function getDescription(): string
    {
        return 'Create a new event class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/event.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Events/' . $name . '.php';
    }
}
