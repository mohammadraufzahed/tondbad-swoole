<?php

declare(strict_types=1);

namespace TondbadSwoole\Core;

use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

class Container
{
    /**
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * @var array<class-string, ReflectionClass>
     */
    private array $reflectionCache = [];

    /**
     * @var array<class-string, list<ReflectionParameter>>
     */
    private array $constructorParametersCache = [];

    /**
     * Bind a service or class into the container.
     */
    public function bind(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Determine if the container has a binding for the given abstract.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Bind a singleton service into the container.
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = function () use ($abstract, $concrete) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = is_callable($concrete) ? $concrete() : $this->resolve($concrete);
            }

            return $this->instances[$abstract];
        };
    }

    /**
     * Resolve a service or class from the container.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @return T|mixed
     * @throws Exception
     */
    public function make(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];

            if (is_callable($binding)) {
                return $binding();
            }

            if (is_string($binding) && class_exists($binding)) {
                return $this->resolve($binding);
            }

            return $binding;
        }

        return $this->resolve($abstract);
    }

    /**
     * Automatically resolve a class's dependencies using reflection.
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     * @throws Exception
     */
    protected function resolve(string $class)
    {
        $reflector = $this->reflectionCache[$class] ??= new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class();
        }

        $parameters = $this->constructorParametersCache[$class] ??= $constructor->getParameters();
        $dependencies = array_map(
            fn(ReflectionParameter $parameter) => $this->resolveParameter($parameter, $class),
            $parameters
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a single constructor parameter.
     *
     * @param class-string $class
     */
    protected function resolveParameter(ReflectionParameter $parameter, string $class): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionedType) {
                if (!$unionedType instanceof ReflectionNamedType || $unionedType->isBuiltin()) {
                    continue;
                }

                try {
                    return $this->make($unionedType->getName());
                } catch (Throwable $e) {
                    continue;
                }
            }
        } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            try {
                return $this->make($type->getName());
            } catch (Throwable $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                if ($parameter->allowsNull()) {
                    return null;
                }

                throw $e;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new Exception("Cannot resolve parameter '\${$parameter->getName()}' for class '{$class}': unresolvable type.");
    }

    /**
     * Call a callable and resolve its parameters from the container.
     *
     * @param callable|array{0: object, 1: string} $callback
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback) && is_object($callback[0])) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
            $args = $this->resolveArgs($reflection, $parameters, $reflection->class ?? '');

            return $reflection->invokeArgs($callback[0], $args);
        }

        if (is_string($callback) && function_exists($callback)) {
            $reflection = new ReflectionFunction($callback);
            $args = $this->resolveArgs($reflection, $parameters);

            return $reflection->invokeArgs($args);
        }

        if ($callback instanceof \Closure) {
            $reflection = new ReflectionFunction($callback);
            $args = $this->resolveArgs($reflection, $parameters);

            return $reflection->invokeArgs($args);
        }

        throw new Exception('Unsupported callback type for container call.');
    }

    /**
     * @return list<mixed>
     */
    private function resolveArgs(\ReflectionFunctionAbstract $reflection, array $parameters, string $class = ''): array
    {
        $args = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];

                continue;
            }

            $args[] = $this->resolveParameter($parameter, $class);
        }

        return $args;
    }
}
