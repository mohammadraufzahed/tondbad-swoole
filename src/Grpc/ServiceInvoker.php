<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use ReflectionMethod;

final class ServiceInvoker
{
    public static function invoke(object $impl, string $methodName, Request $request): mixed
    {
        $reflection = self::resolveMethod($impl, $methodName);

        if ($reflection === null) {
            throw new StatusException(Status::unimplemented("Method {$methodName} not implemented"));
        }

        $args = self::buildArguments($reflection, $request);

        return $reflection->invokeArgs($impl, $args);
    }

    private static function resolveMethod(object $impl, string $methodName): ?ReflectionMethod
    {
        $class = new \ReflectionClass($impl);

        foreach ([lcfirst($methodName), $methodName] as $candidate) {
            if ($class->hasMethod($candidate)) {
                $method = $class->getMethod($candidate);

                if ($method->isPublic() && !$method->isStatic()) {
                    return $method;
                }
            }
        }

        return null;
    }

    private static function buildArguments(ReflectionMethod $method, Request $request): array
    {
        $args = [];
        $messageClass = get_class($request->message);

        foreach ($method->getParameters() as $param) {
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
            } elseif ($type instanceof \ReflectionNamedType && $type->getName() === $messageClass) {
                $args[] = $request->message;
            } elseif ($type instanceof \ReflectionNamedType && is_subclass_of($request->message, $type->getName())) {
                $args[] = $request->message;
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $args;
    }
}
