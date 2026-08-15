<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;

#[AsCommand('make:job', 'Create a new job class.')]
class MakeJobCommand extends MakeCommand
{
    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/job.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Jobs/' . $name . '.php';
    }
}
