<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:policy', 'Create a new policy class.')]
class MakePolicyCommand extends MakeCommand
{
    protected function getNameSuffixes(): array
    {
        return ['Policy'];
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/policy.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Policies/' . $name . 'Policy.php';
    }
}
