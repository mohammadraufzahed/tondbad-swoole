<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:provider', 'Create a new service provider class.')]
class MakeProviderCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['ServiceProvider', 'Provider'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/provider.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Providers/' . $name . 'ServiceProvider.php';
    }
}
