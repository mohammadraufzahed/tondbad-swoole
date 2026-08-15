<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:middleware', 'Create a new HTTP middleware class.')]
class MakeMiddlewareCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['Middleware'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/middleware.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Http/Middleware/' . $name . 'Middleware.php';
    }
}
