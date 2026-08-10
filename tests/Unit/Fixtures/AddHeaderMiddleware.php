<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class AddHeaderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): void
    {
        $response->header('X-Test-Header', 'tondbad');
        $next($request, $response);
    }
}
