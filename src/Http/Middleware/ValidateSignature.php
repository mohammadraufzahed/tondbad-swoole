<?php

declare(strict_types=1);

namespace TondbadSwoole\Http\Middleware;

use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\SignedUrl;

class ValidateSignature implements MiddlewareInterface
{
    private readonly SignedUrl $signedUrl;

    public function __construct(Config $config)
    {
        $this->signedUrl = new SignedUrl((string) $config->get('app.key', ''));
    }

    public function process(Request $request, Response $response, callable $next): void
    {
        if (!$this->signedUrl->validate($request->path(), $request->queries())) {
            $response->status(403)->end('Invalid or expired signature.');

            return;
        }

        $next($request, $response);
    }
}
