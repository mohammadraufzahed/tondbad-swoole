<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use ReflectionClass;
use ReflectionMethod;
use TondbadSwoole\Grpc\Attributes\AsGrpcService;
use TondbadSwoole\Grpc\Attributes\GrpcMethod;

abstract class AttributeServiceAdapter implements BindableService
{
    public function bindService(): ServiceDefinition
    {
        $reflection = new ReflectionClass($this);
        $serviceAttribute = $reflection->getAttributes(AsGrpcService::class)[0] ?? null;

        if ($serviceAttribute === null) {
            throw new \RuntimeException('Missing #[AsGrpcService] attribute on ' . $reflection->getName());
        }

        /** @var AsGrpcService $service */
        $service = $serviceAttribute->newInstance();
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodAttribute = $method->getAttributes(GrpcMethod::class)[0] ?? null;

            if ($methodAttribute === null) {
                continue;
            }

            /** @var GrpcMethod $descriptor */
            $descriptor = $methodAttribute->newInstance();

            $handler = function (Request $request) use ($method, $descriptor): Response {
                $args = $this->buildArguments($method, $request, $descriptor);

                $result = $method->invokeArgs($this, $args);

                if ($result instanceof Response) {
                    return $result;
                }

                if ($result === null) {
                    $result = new $descriptor->output();
                }

                return new Response($result, Status::ok());
            };

            $methods[] = new MethodDescriptor(
                $descriptor->name,
                $descriptor->input,
                $descriptor->output,
                $descriptor->clientStreaming,
                $descriptor->serverStreaming,
                $handler,
            );
        }

        return new ServiceDefinition(
            $service->name,
            $methods,
            $service->package,
        );
    }

    private function buildArguments(\ReflectionMethod $method, Request $request, GrpcMethod $descriptor): array
    {
        $args = [];
        $params = $method->getParameters();

        foreach ($params as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && $type->getName() === Context::class) {
                $args[] = $request->context;
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $args[] = $request;
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === Metadata::class) {
                $args[] = $request->metadata;
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === ServerCallInfo::class) {
                $args[] = new ServerCallInfo($request->service, $request->method, $request->metadata, $request->deadline);
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === StreamReader::class) {
                $args[] = $request->stream;
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === StreamWriter::class) {
                $args[] = $request->writer;
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === $descriptor->input) {
                $args[] = $request->message;
            } elseif ($type instanceof \ReflectionNamedType && is_subclass_of($request->message, $type->getName())) {
                $args[] = $request->message;
            } else {
                $args[] = $param->isOptional() ? $param->getDefaultValue() : null;
            }
        }

        return $args;
    }
}
