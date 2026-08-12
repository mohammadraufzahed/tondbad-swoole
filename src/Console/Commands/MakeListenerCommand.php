<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeListenerCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:listener';
    }

    public function getDescription(): string
    {
        return 'Create a new event listener class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/listener.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Listeners/' . $name . '.php';
    }
}
