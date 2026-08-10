<?php

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
     * @var Container|null
     */
    private static ?Container $instance = null;
    /**
     * @var array<string, mixed>
     */
    protected array $bindings = [];
    /**
     * @var array<string, mixed>
     */
    protected array $instances = [];

    public static function create(): self
    {
        if (!self::$instance)
            self::$instance = new self;
        return self::$instance;
    }

    /**
     * Bind a service or class into the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function bind(string $abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Determine if the container has a binding for the given abstract.
     *
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Bind a singleton service into the container.
     *
     * @param string $abstract
     * @param callable|string $concrete
     * @return void
     */
    public function singleton(string $abstract, callable|string $concrete)
    {
        $this->bindings[$abstract] = function () use ($abstract, $concrete) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = is_callable($concrete) ? $concrete() : $this->resolve($concrete);
            }
            return $this->instances[$abstract];
        };
    }

    /**
     * Automatically resolve a class's dependencies using reflection.
     * @template T
     * @param class-string<T> $class
     * @return T
     * @throws Exception
     */
    protected function resolve(string $class)
    {
        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = array_map(fn(ReflectionParameter $parameter) => $this->resolveParameter($parameter), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a single constructor parameter.
     *
     * @param ReflectionParameter $parameter
     * @return mixed
     * @throws Exception
     */
    protected function resolveParameter(ReflectionParameter $parameter): mixed
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

        throw new Exception("Cannot resolve parameter '{$parameter->getName()}': unresolvable type.");
    }

    /**
     * Resolve a service or class from the container.
     * @template T
     * @param class-string<T> $abstract
     * @return T
     * @throws Exception
     */
    public function make(string $abstract)
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
}
