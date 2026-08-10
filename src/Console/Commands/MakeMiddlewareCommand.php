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

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class {Name}Middleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): void
    {
        $next($request, $response);
    }
}

STUB;
    }

    protected function getDefaultPath(string $name): string
    {
        return $this->basePath . '/app/Http/Middleware/' . $name . 'Middleware.php';
    }
}
