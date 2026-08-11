<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

class MakeMiddlewareCommand extends MakeCommand
{
    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Create a new HTTP middleware class.';
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
