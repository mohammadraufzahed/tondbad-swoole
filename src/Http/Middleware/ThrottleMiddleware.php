<?php

declare(strict_types=1);

namespace TondbadSwoole\Http\Middleware;

use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Queue\RateLimiter\RateLimiterInterface;

class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    public function process(Request $request, Response $response, callable $next): void
    {
        $limiter = $this->rateLimiter ?? app()?->container->make(RateLimiterInterface::class);

        if ($limiter === null) {
            $next($request, $response);

            return;
        }

        $key = 'throttle:' . md5($request->path() . ':' . $this->clientIdentifier($request));

        if (!$limiter->attempt($key, $this->maxRequests, $this->windowSeconds)) {
            $response->status(429)->end('Too Many Requests');

            return;
        }

        $next($request, $response);
    }

    private function clientIdentifier(Request $request): string
    {
        $swoole = $request->getSwooleRequest();

        return $swoole->header['x-forwarded-for']
            ?? $swoole->server['remote_addr']
            ?? $swoole->server['x-real-ip']
            ?? 'unknown';
    }
}
