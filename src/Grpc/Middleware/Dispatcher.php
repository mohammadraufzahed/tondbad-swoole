<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Middleware;

use OpenSwoole\GRPC\Constant;
use OpenSwoole\GRPC\MessageInterface;
use OpenSwoole\GRPC\Request as OpenSwooleRequest;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Response as OpenSwooleResponse;
use OpenSwoole\GRPC\Middleware\MiddlewareInterface;
use OpenSwoole\GRPC\Status as OpenSwooleStatus;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Grpc\Context;
use TondbadSwoole\Grpc\InterceptorChain;
use TondbadSwoole\Grpc\Metadata;
use TondbadSwoole\Grpc\Request;
use TondbadSwoole\Grpc\Response;
use TondbadSwoole\Grpc\ServerCallInfo;
use TondbadSwoole\Grpc\ServiceRegistry;
use TondbadSwoole\Grpc\Status;
use TondbadSwoole\Grpc\StatusException;

final class Dispatcher implements MiddlewareInterface
{
    /** @param \TondbadSwoole\Grpc\UnaryServerInterceptor[] $interceptors */
    public function __construct(
        private readonly Container $container,
        private readonly array $interceptors,
    ) {
    }

    public function process(MessageInterface $request, RequestHandlerInterface $handler): MessageInterface
    {
        if (!$request instanceof OpenSwooleRequest) {
            return $handler->handle($request) ?? $this->errorResponse($request->getContext(), Status::internal('Dispatcher received non-Request message'));
        }

        $context = $request->getContext();
        $workerContext = $context->getValue('WORKER_CONTEXT');

        if (!$workerContext instanceof \OpenSwoole\GRPC\Context) {
            return $this->errorResponse($context, Status::internal('Missing worker context'));
        }

        $registry = $workerContext->getValue('tondbad.grpc.registry');

        if (!$registry instanceof ServiceRegistry) {
            return $this->errorResponse($context, Status::internal('Missing gRPC service registry'));
        }

        $rawRequest = $context->getValue(\OpenSwoole\Http\Request::class);
        $headers = $rawRequest?->header ?? [];
        $metadata = Metadata::fromArray($headers);

        $serviceName = $this->normalizeService($request->getService());
        $methodName = $request->getMethod();

        $definition = $registry->getDefinition($serviceName);

        if ($definition === null) {
            return $this->errorResponse($context, Status::unimplemented("Service {$serviceName} not found"));
        }

        $method = $definition->getMethod($methodName);

        if ($method === null || $method->handler === null) {
            return $this->errorResponse($context, Status::unimplemented("Method {$serviceName}/{$methodName} not found"));
        }

        $contentType = $context->getValue(Constant::CONTENT_TYPE);
        $payload = $request->getPayload();

        try {
            $input = $this->deserialize($method->inputClass, $payload, $contentType);
        } catch (\Throwable $e) {
            return $this->errorResponse($context, Status::invalidArgument('Failed to deserialize request: ' . $e->getMessage(), previous: $e));
        }

        $deadline = $this->parseDeadline($metadata->first('grpc-timeout'));
        $grpcContext = new Context($context->getValues());
        $grpcRequest = new Request(
            $definition->name,
            $method->name,
            $input,
            $metadata,
            $grpcContext,
            $deadline,
        );

        $info = new ServerCallInfo($definition->name, $method->name, $metadata, $deadline);

        try {
            $response = (new InterceptorChain($this->resolveInterceptors(), $info))->handle($grpcRequest, $method->handler);
        } catch (StatusException $e) {
            return $this->errorResponse($context, $e->status);
        } catch (\Throwable $e) {
            return $this->errorResponse($context, Status::internal($e->getMessage(), previous: $e));
        }

        try {
            $output = $this->serialize($response->message, $contentType);
        } catch (\Throwable $e) {
            return $this->errorResponse($context, Status::internal('Failed to serialize response: ' . $e->getMessage(), previous: $e));
        }

        $newContext = $context
            ->withValue(Constant::GRPC_STATUS, $response->status->code)
            ->withValue(Constant::GRPC_MESSAGE, $response->status->message);

        if ($response->metadata !== null) {
            // Metadata is not written back to OpenSwoole response in this version; stored for future use.
            $newContext = $newContext->withValue('tondbad.grpc.response_metadata', $response->metadata);
        }

        return new OpenSwooleResponse($newContext, $output);
    }

    private function normalizeService(string $serviceName): string
    {
        return ltrim($serviceName, '/');
    }

    private function errorResponse(\OpenSwoole\GRPC\Context $context, Status $status): OpenSwooleResponse
    {
        $newContext = $context
            ->withValue(Constant::GRPC_STATUS, $status->code)
            ->withValue(Constant::GRPC_MESSAGE, $status->message);

        return new OpenSwooleResponse($newContext, '');
    }

    private function deserialize(string $class, string $payload, ?string $contentType): object
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Message class {$class} not found");
        }

        /** @var \Google\Protobuf\Internal\Message $message */
        $message = new $class();

        if ($payload === '') {
            return $message;
        }

        if ($contentType === 'application/grpc+json') {
            $message->mergeFromJsonString($payload);
        } else {
            $message->mergeFromString($payload);
        }

        return $message;
    }

    private function serialize(object $message, ?string $contentType): string
    {
        if (!$message instanceof \Google\Protobuf\Internal\Message) {
            throw new \InvalidArgumentException('Response message must be a protobuf message');
        }

        if ($contentType === 'application/grpc+json') {
            return $message->serializeToJsonString();
        }

        return $message->serializeToString();
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
        } elseif (str_ends_with($timeout, 'S')) {
            // seconds
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

    /** @return \TondbadSwoole\Grpc\UnaryServerInterceptor[] */
    private function resolveInterceptors(): array
    {
        $resolved = [];

        foreach ($this->interceptors as $interceptor) {
            $resolved[] = is_object($interceptor) ? $interceptor : $this->container->make($interceptor);
        }

        return $resolved;
    }
}
