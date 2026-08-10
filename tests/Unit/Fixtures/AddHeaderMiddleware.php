<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Contracts\MiddlewareInterface;

class AddHeaderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): void
    {
        $response->header('X-Test-Header', 'tondbad');
        $next($request, $response);
    }
}
