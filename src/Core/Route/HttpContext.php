<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class HttpContext
{
    public function __construct(
        public Request $request,
        public Response $response
    ) {
    }
}
