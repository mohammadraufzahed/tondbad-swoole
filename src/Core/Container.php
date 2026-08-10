<?php

declare(strict_types=1);

namespace TondbadSwoole\Core;

use Exception;
use ReflectionClass;
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
}
