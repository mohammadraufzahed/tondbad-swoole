<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeControllerCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a new controller class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/controller.stub';
    }

    protected function getNameSuffixes(): array
    {
        return ['Controller'];
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Http/Controllers/' . $name . 'Controller.php';
    }
}
