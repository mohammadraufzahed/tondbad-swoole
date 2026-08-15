<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:model', 'Create a new model class.')]
class MakeModelCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/model.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Models/' . $name . '.php';
    }
}
