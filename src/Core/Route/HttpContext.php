<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

class HttpContext
{
    public function __construct(
        public Request $request,
        public Response $response
    ) {
    }
}
