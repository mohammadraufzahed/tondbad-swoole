<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

interface UnaryServerInterceptor
{
    public function intercept(Request $request, callable $handler, ServerCallInfo $info): Response;
}
