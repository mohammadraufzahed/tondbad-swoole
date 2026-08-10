<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

interface MiddlewareInterface
{
    /**
     * Process an incoming HTTP request and optionally delegate to the next handler.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next A callable accepting a Request and Response and returning void.
     */
    public function process(Request $request, Response $response, callable $next): void;
}
