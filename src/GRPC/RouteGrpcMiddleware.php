<?php

declare(strict_types=1);

namespace TondbadSwoole\GRPC;

use OpenSwoole\GRPC\MessageInterface;
use OpenSwoole\GRPC\Middleware\MiddlewareInterface;
use OpenSwoole\GRPC\Request;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Response;
use OpenSwoole\GRPC\Status;
use TondbadSwoole\Core\Route\RouteDispatcher;

class RouteGrpcMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RouteDispatcher $dispatcher)
    {
    }

    public function process(MessageInterface $request, RequestHandlerInterface $handler): MessageInterface
    {
        if (!$request instanceof Request) {
            return $handler->handle($request);
        }

        $context = $request->getContext();
        $rawRequest = $context->getValue(\OpenSwoole\Http\Request::class);

        if (!$rawRequest instanceof \OpenSwoole\Http\Request) {
            return $handler->handle($request);
        }

        $service = ltrim($request->getService(), '/');
        $path = '/' . $service . '/' . $request->getMethod();

        $contentType = $rawRequest->header['content-type'] ?? 'application/grpc';
        $payload = $request->getPayload();

        [$post, $rawContent, $httpContentType] = $this->prepareHttpPayload($contentType, $payload);

        $httpRequest = new GrpcHttpRequest(
            $rawContent,
            [
                'request_method' => 'POST',
                'request_uri' => $path,
                'remote_addr' => $rawRequest->server['remote_addr'] ?? '127.0.0.1',
                'remote_port' => $rawRequest->server['remote_port'] ?? '0',
                'server_protocol' => 'HTTP/2',
                'request_time' => time(),
            ],
            array_merge($rawRequest->header ?? [], ['content-type' => $httpContentType]),
            $rawRequest->get ?? [],
            $post,
            $rawRequest->cookie ?? []
        );

        $httpResponse = new GrpcHttpResponse();

        $this->dispatcher->dispatch($httpRequest, $httpResponse);

        [$grpcStatus, $grpcMessage] = $this->mapHttpStatus($httpResponse->capturedStatus, $httpResponse->capturedBody);

        $newContext = $context
            ->withValue(\OpenSwoole\GRPC\Constant::GRPC_STATUS, $grpcStatus)
            ->withValue(\OpenSwoole\GRPC\Constant::GRPC_MESSAGE, $grpcMessage);

        return new Response($newContext, $httpResponse->capturedBody);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function prepareHttpPayload(string $contentType, string $payload): array
    {
        if ($contentType === 'application/grpc+json') {
            $json = json_decode($payload, true);
            $post = is_array($json) ? $json : [];

            return [$post, $payload, 'application/json'];
        }

        return [[], $payload, 'application/octet-stream'];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function mapHttpStatus(int $status, string $body): array
    {
        $map = [
            200 => Status::OK,
            401 => Status::UNAUTHENTICATED,
            403 => Status::PERMISSION_DENIED,
            404 => Status::NOT_FOUND,
            405 => Status::UNIMPLEMENTED,
            422 => Status::INVALID_ARGUMENT,
            429 => Status::RESOURCE_EXHAUSTED,
            500 => Status::INTERNAL,
        ];

        $grpcStatus = $map[$status] ?? ($status >= 500 ? Status::INTERNAL : Status::UNKNOWN);

        if ($grpcStatus === Status::OK) {
            return [Status::OK, ''];
        }

        $message = $body;
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded['message'])) {
                $message = (string) $decoded['message'];
            } elseif (is_array($decoded) && isset($decoded['error'])) {
                $message = (string) $decoded['error'];
            }
        } catch (\JsonException) {
        }

        return [$grpcStatus, $message];
    }
}
