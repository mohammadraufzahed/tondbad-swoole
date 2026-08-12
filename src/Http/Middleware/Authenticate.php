<?php

declare(strict_types=1);

namespace TondbadSwoole\Http\Middleware;

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class Authenticate implements MiddlewareInterface
{
    public function __construct(
        private readonly ?string $guard = null,
    ) {
    }

    public static function guard(string $guard): self
    {
        return new self($guard);
    }

    public function process(Request $request, Response $response, callable $next): void
    {
        $auth = $this->guard === null ? auth() : auth($this->guard);

        if (!$auth->check()) {
            throw new AuthorizationException();
        }

        $next($request, $response);
    }
}
