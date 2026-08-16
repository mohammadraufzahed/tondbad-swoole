<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use OpenSwoole\HTTP\Request as SwooleRequest;
use OpenSwoole\HTTP\Response as SwooleResponse;
use TondbadSwoole\Core\Container;

final class Dispatcher
{
    /** @param class-string<ServerInterceptor>[] $interceptors */
    public function __construct(
        private readonly Container $container,
        private readonly array $interceptors = [],
    ) {
    }

    public function dispatch(SwooleRequest $rawRequest, SwooleResponse $rawResponse, ServiceRegistry $registry): void
    {
        $status = Status::ok();
        $contentType = 'application/grpc';

        try {
            $this->validateRequest($rawRequest);

            $contentType = $rawRequest->header['content-type'] ?? 'application/grpc';
            $metadata = Metadata::fromArray($rawRequest->header ?? []);

            $path = $rawRequest->server['request_uri'] ?? '';
            $parts = explode('/', ltrim($path, '/'), 2);

            if (count($parts) !== 2) {
                throw new StatusException(Status::unimplemented("Invalid gRPC path: {$path}"));
            }

            [$serviceName, $methodName] = $parts;

            $definition = $registry->getDefinition($serviceName);

            if ($definition === null) {
                throw new StatusException(Status::unimplemented("Service {$serviceName} not found"));
            }

            $method = $definition->getMethod($methodName);

            if ($method === null || $method->handler === null) {
                throw new StatusException(Status::unimplemented("Method {$serviceName}/{$methodName} not found"));
            }

            $body = $rawRequest->getContent() ?: '';
            $messages = Frame::decodeAll($body, $method->inputClass);

            $firstMessage = $messages[0] ?? new $method->inputClass();
            $streamReader = $method->clientStreaming ? new StreamReader($messages) : null;

            $context = new Context([
                SwooleRequest::class => $rawRequest,
                SwooleResponse::class => $rawResponse,
                'tondbad.grpc.content_type' => $contentType,
                'tondbad.grpc.descriptor_set' => $this->descriptorSetPath(),
                ServiceRegistry::class => $registry,
            ]);

            $deadline = $this->parseDeadline($metadata->first('grpc-timeout'));

            $streamWriter = $method->serverStreaming ? new StreamWriter($context, $contentType) : null;

            $request = new Request(
                $definition->name,
                $method->name,
                $firstMessage,
                $metadata,
                $context,
                $deadline,
                $streamReader,
                $streamWriter,
            );

            $info = new ServerCallInfo($definition->name, $method->name, $metadata, $deadline);

            $response = (new InterceptorChain($this->resolveInterceptors(), $info))->handle($request, $method->handler);

            if ($streamWriter !== null) {
                $streamWriter->close($response->status);

                return;
            }

            $this->send($rawResponse, $response->message, $contentType, $response->status);
        } catch (StatusException $e) {
            $this->send($rawResponse, null, $contentType, $e->status);
        } catch (\Throwable $e) {
            $this->send($rawResponse, null, $contentType, Status::internal($e->getMessage(), previous: $e));
        }
    }

    private function validateRequest(SwooleRequest $request): void
    {
        $contentType = $request->header['content-type'] ?? '';

        if ($contentType !== 'application/grpc'
            && $contentType !== 'application/grpc+proto'
            && $contentType !== 'application/grpc+json'
        ) {
            throw new StatusException(Status::internal("Content-type not supported: {$contentType}"));
        }

        $te = $request->header['te'] ?? '';

        if (strtolower($te) !== 'trailers') {
            throw new StatusException(Status::internal('Missing TE: trailers header'));
        }
    }

    private function send(SwooleResponse $rawResponse, ?object $message, string $contentType, Status $status): void
    {
        $rawResponse->header('content-type', $contentType);
        $rawResponse->header('trailer', 'grpc-status, grpc-message');
        $rawResponse->trailer('grpc-status', (string) $status->code);
        $rawResponse->trailer('grpc-message', $status->message);

        $payload = $message !== null ? Frame::encode($message, $contentType) : '';

        $rawResponse->end($payload);
    }

    private function parseDeadline(?string $timeout): ?\DateTimeImmutable
    {
        if ($timeout === null || $timeout === '') {
            return null;
        }

        $value = (int) $timeout;

        if (str_ends_with($timeout, 'H')) {
            $value *= 3600;
        } elseif (str_ends_with($timeout, 'M')) {
            $value *= 60;
        } elseif (str_ends_with($timeout, 'm')) {
            $value = (int) round($value / 1000);
        } elseif (str_ends_with($timeout, 'u')) {
            $value = (int) round($value / 1000000);
        } elseif (str_ends_with($timeout, 'n')) {
            $value = (int) round($value / 1000000000);
        }

        try {
            return (new \DateTimeImmutable())->modify("+{$value} seconds");
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return ServerInterceptor[] */
    private function descriptorSetPath(): string
    {
        if ($this->container->has(\TondbadSwoole\Core\Config::class)) {
            /** @var \TondbadSwoole\Core\Config $config */
            $config = $this->container->make(\TondbadSwoole\Core\Config::class);
            $path = $config->get('grpc.descriptor_set');

            if ($path !== null && $path !== '') {
                return $path;
            }
        }

        return getcwd() . '/storage/cache/grpc/descriptors.pb';
    }

    private function resolveInterceptors(): array
    {
        $resolved = [];

        foreach ($this->interceptors as $interceptor) {
            $resolved[] = is_object($interceptor) ? $interceptor : $this->container->make($interceptor);
        }

        return $resolved;
    }
}
