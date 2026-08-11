<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeJobCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:job';
    }

    public function getDescription(): string
    {
        return 'Create a new job class.';
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../../stubs/job.stub';
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Jobs/' . $name . '.php';
    }
}
