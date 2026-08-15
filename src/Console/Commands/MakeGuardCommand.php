<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:guard', 'Create a new guard factory class.')]
class MakeGuardCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['GuardFactory', 'Guard'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/guard.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Auth/Guards/' . $name . 'GuardFactory.php';
    }
}
