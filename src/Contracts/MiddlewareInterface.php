<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

interface MiddlewareInterface
{
    /**
     * Process an incoming request and optionally pass it to the next middleware.
     *
     * The $next callable accepts a Request and Response and should be invoked to
     * continue the pipeline.
     */
    public function process(Request $request, Response $response, callable $next): void;
}
