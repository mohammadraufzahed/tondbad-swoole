<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeRequestCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:request';
    }

    public function getDescription(): string
    {
        return 'Create a new form request class.';
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
