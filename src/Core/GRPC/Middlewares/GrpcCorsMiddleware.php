<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\GRPC\Middlewares;

use OpenSwoole\GRPC\Middleware\MiddlewareInterface;
use OpenSwoole\GRPC\Request;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Response;
use TondbadSwoole\Core\Config;

class GrpcCorsMiddleware implements MiddlewareInterface
{
    private readonly array $allowedHosts;

    public function __construct(
        private readonly Config $config
    ) {
        $this->allowedHosts = $this->config->get('grpc.allowed_hosts', ['*']);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $ctx = $request->getContext();
        $rawRequest = $ctx->getValue(\OpenSwoole\Http\Request::class);

        $host = $rawRequest->header['host'] ?? '';

        if (!$this->isHostAllowed($host)) {
            return new Response($ctx, 'Forbidden: Host not allowed');
        }

        return $handler->handle($request);
    }

    private function isHostAllowed(string $host): bool
    {
        $host = explode(':', $host)[0];

        if (in_array('*', $this->allowedHosts, true)) {
            return true;
        }

        return in_array($host, $this->allowedHosts, true);
    }
}
