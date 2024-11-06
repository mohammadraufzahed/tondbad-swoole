<?php

namespace TondbadSwoole\Core\GRPC\Middlewares;

use OpenSwoole\GRPC\Middleware\MiddlewareInterface;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Request;
use OpenSwoole\GRPC\Response;
use OpenSwoole\GRPC\Status;
use TondbadSwoole\Core\Config;

class CorsMiddleware implements MiddlewareInterface
{
    private readonly array $allowedHosts;

    public function __construct()
    {
        // Fetch allowed hosts from config, with '*' allowing all by default
        $this->allowedHosts = Config::get('grpc.allowed_hosts', ['*']);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $ctx = $request->getContext();
        $rawRequest = $ctx->getValue(\OpenSwoole\Http\Request::class);

        // Get the host header from the raw HTTP request
        $host = $rawRequest->header['host'] ?? '';

        echo print_r($rawRequest->header, true) . "\n";

        // Check if the host is allowed
        if (!$this->isHostAllowed($host)) {
            // Create a response with a gRPC status of PermissionDenied
            $response = new Response($ctx, 'Forbidden: Host not allowed');
            return $response;
        }

        // Host is allowed, proceed with the request
        return $handler->handle($request);
    }

    private function isHostAllowed(string $host): bool
    {
        $host = explode(':', $host)[0];
        echo $host . "\n";
        // Allow all hosts if '*' is in allowedHosts
        if (in_array('*', $this->allowedHosts, true)) {
            return true;
        }

        // Check if the specific host is allowed
        return in_array($host, $this->allowedHosts, true);
    }
}
