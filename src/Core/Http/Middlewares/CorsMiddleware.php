<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Http\Middlewares;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Config;

class CorsMiddleware implements MiddlewareInterface
{
    private readonly array $allowedOrigins;
    private readonly array $allowedOriginsPatterns;
    private readonly array $allowedMethods;
    private readonly array $allowedHeaders;
    private readonly array $exposedHeaders;
    private readonly int $maxAge;
    private readonly bool $supportsCredentials;

    public function __construct(private readonly Config $config)
    {
        $cors = $config->get('cors', []);

        $this->allowedOrigins = $cors['allowed_origins'] ?? ['*'];
        $this->allowedOriginsPatterns = $cors['allowed_origins_patterns'] ?? [];
        $this->allowedMethods = $cors['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $cors['allowed_headers'] ?? ['*'];
        $this->exposedHeaders = $cors['exposed_headers'] ?? [];
        $this->maxAge = $cors['max_age'] ?? 86400;
        $this->supportsCredentials = $cors['supports_credentials'] ?? false;
    }

    public function process(Request $request, Response $response, callable $next): void
    {
        $origin = $request->header['origin'] ?? '';

        if ($this->isOriginAllowed($origin)) {
            $this->setCorsHeaders($response, $origin);
        }

        if (strtoupper($request->server['request_method'] ?? '') === 'OPTIONS') {
            $response->status(204);
            $response->end();

            return;
        }

        $next($request, $response);
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true) || in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }

        foreach ($this->allowedOriginsPatterns as $pattern) {
            if (preg_match($pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    private function setCorsHeaders(Response $response, string $origin): void
    {
        if (in_array('*', $this->allowedOrigins, true) && $origin === '') {
            $response->header('Access-Control-Allow-Origin', '*');
        } else {
            $response->header('Access-Control-Allow-Origin', $origin);
        }

        $response->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        $response->header('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->supportsCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->exposedHeaders !== []) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }
    }
}
