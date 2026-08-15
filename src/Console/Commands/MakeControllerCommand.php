<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:controller', 'Create a new controller class.')]
class MakeControllerCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['Controller'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/controller.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Http/Controllers/' . $name . 'Controller.php';
    }
}
