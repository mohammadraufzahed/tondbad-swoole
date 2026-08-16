<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class InterceptorChain
{
    /** @param ServerInterceptor[] $interceptors */
    public function __construct(
        private readonly array $interceptors,
        private readonly ServerCallInfo $info,
    ) {
    }

    public function handle(Request $request, callable $handler): Response
    {
        $next = $this->wrap($handler);

        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = function (Request $request) use ($interceptor, $next): Response {
                return $interceptor->intercept($request, $next, $this->info);
            };
        }

        return $next($request);
    }

    private function wrap(callable $handler): callable
    {
        return function (Request $request) use ($handler): Response {
            $result = $handler($request);

            if ($result instanceof Response) {
                return $result;
            }

            return new Response($result ?? null, Status::ok());
        };
    }
}
