<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Exception;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Http\FormRequest;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class HandlerInvoker
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array|callable $handler
     * @param array<string, string> $vars
     */
    public function invoke(array|callable $handler, Request $request, Response $response, array $vars): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = $this->container->make($class);
            $reflection = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveDependencies($reflection, $request, $response, $vars);
            $reflection->invokeArgs($instance, $dependencies);

            return;
        }

        $reflection = new ReflectionFunction($handler);
        $dependencies = $this->resolveDependencies($reflection, $request, $response, $vars);
        $reflection->invokeArgs($dependencies);
    }

    /**
     * @return list<mixed>
     */
    private function resolveDependencies(ReflectionFunctionAbstract $reflection, Request $request, Response $response, array $vars): array
    {
        $dependencies = [];

        foreach ($reflection->getParameters() as $param) {
            $dependencies[] = $this->resolveParameter($param, $request, $response, $vars);
        }

        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $param, Request $request, Response $response, array $vars): mixed
    {
        $type = $param->getType();
        $name = $param->getName();

        if ($type instanceof ReflectionNamedType) {
            $value = $this->tryResolveNamedType($param, $type, $request, $response, $vars);

            if ($value !== null || ($value === null && $type->allowsNull())) {
                return $value;
            }
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionedType) {
                if (!$unionedType instanceof ReflectionNamedType) {
                    continue;
                }

                try {
                    $value = $this->tryResolveNamedType($param, $unionedType, $request, $response, $vars);

                    if ($value !== null || ($unionedType->getName() === 'null')) {
                        return $value;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        if (array_key_exists($name, $vars)) {
            return $vars[$name];
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw new Exception("Cannot resolve parameter '{$name}'");
    }

    private function tryResolveNamedType(
        ReflectionParameter $param,
        ReflectionNamedType $type,
        Request $request,
        Response $response,
        array $vars
    ): mixed {
        $typeName = $type->getName();
        $name = $param->getName();

        if ($typeName === Request::class) {
            return $request;
        }

        if ($typeName === Response::class) {
            return $response;
        }

        if ($typeName === SwooleRequest::class) {
            return $request->getSwooleRequest();
        }

        if ($typeName === SwooleResponse::class) {
            return $response->getSwooleResponse();
        }

        if (is_subclass_of($typeName, FormRequest::class)) {
            return new $typeName($request->getSwooleRequest());
        }

        if (array_key_exists($name, $vars)) {
            return $this->castValue($vars[$name], $type);
        }

        if (!$type->isBuiltin()) {
            return $this->container->make($typeName);
        }

        return null;
    }

    private function castValue(mixed $value, ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => is_bool($value) ? $value : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }
}
