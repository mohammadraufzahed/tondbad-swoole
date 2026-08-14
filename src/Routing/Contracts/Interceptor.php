<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Contracts;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

interface Interceptor
{
    /**
     * @param callable(): mixed $next
     */
    public function intercept(Request $request, Response $response, callable $next): mixed;
}
