<?php

declare(strict_types=1);

namespace TondbadSwoole\Http\Middleware;

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class VerifyCsrfToken implements MiddlewareInterface
{
    private array $safeMethods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    public function __construct(private readonly ?string $guard = null)
    {
    }

    public function process(Request $request, Response $response, callable $next): void
    {
        if (in_array(strtoupper($request->method()), $this->safeMethods, true)) {
            $next($request, $response);

            return;
        }

        $manager = auth($this->guard);

        if (!$manager->check()) {
            throw new AuthorizationException('CSRF validation requires an authenticated session.');
        }

        $session = $manager->session();

        if ($session === null || $session->antiCsrf === null) {
            throw new AuthorizationException('Session does not support CSRF validation.');
        }

        $token = $request->header('X-CSRF-Token')
            ?? $request->all()['_token']
            ?? $request->query('csrf_token')
            ?? '';

        if (!is_string($token) || !hash_equals($session->antiCsrf, $token)) {
            throw new AuthorizationException('Invalid CSRF token.');
        }

        $next($request, $response);
    }
}
