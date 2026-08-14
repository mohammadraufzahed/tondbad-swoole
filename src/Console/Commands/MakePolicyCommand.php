<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakePolicyCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:policy';
    }

    public function getDescription(): string
    {
        return 'Create a new policy class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/policy.stub';
    }

    protected function getNameSuffixes(): array
    {
        return ['Policy'];
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Policies/' . $name . 'Policy.php';
    }
}
