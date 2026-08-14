<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeProviderCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:provider';
    }

    public function getDescription(): string
    {
        return 'Create a new service provider class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/provider.stub';
    }

    protected function getNameSuffixes(): array
    {
        return ['ServiceProvider', 'Provider'];
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Providers/' . $name . 'ServiceProvider.php';
    }
}
