<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeGuardCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:guard';
    }

    public function getDescription(): string
    {
        return 'Create a new guard factory class.';
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
