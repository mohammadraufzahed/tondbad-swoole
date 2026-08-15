<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:request', 'Create a new form request class.')]
class MakeRequestCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['Request'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/request.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Http/Requests/' . $name . 'Request.php';
    }
}
