<?php

namespace TondbadSwoole\Core;

use Exception;
use ReflectionClass;
use ReflectionParameter;

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
        $dependencies = array_map(function (ReflectionParameter $parameter) {
            $type = $parameter->getType();
            if (!$type || $type->isBuiltin()) {
                throw new Exception("Cannot resolve non-class type: " . $parameter->getName());
            }
            return $this->make($type->getName());
        }, $parameters);

        return $reflector->newInstanceArgs($dependencies);
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
            return is_callable($this->bindings[$abstract]) ? ($this->bindings[$abstract])() : $this->bindings[$abstract];
        }

        return $this->resolve($abstract);
    }
}
