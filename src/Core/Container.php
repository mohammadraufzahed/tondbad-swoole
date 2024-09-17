<?php

namespace TondbadSwoole\Core;

use ReflectionClass;
use Exception;

class Container
{
    protected array $bindings = [];
    protected array $instances = [];

    /**
     * Bind a service or class into the container.
     *
     * @param string $abstract
     * @param mixed $concrete
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
     */
    public function singleton(string $abstract, $concrete)
    {
        $this->bindings[$abstract] = function () use ($concrete) {
            static $instance;
            if (!$instance) {
                $instance = is_callable($concrete) ? $concrete() : $this->resolve($concrete);
            }
            return $instance;
        };
    }

    /**
     * Resolve a service or class from the container.
     *
     * @param string $abstract
     * @return mixed
     */
    public function make(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return is_callable($this->bindings[$abstract]) ? ($this->bindings[$abstract])() : $this->bindings[$abstract];
        }

        return $this->resolve($abstract);
    }

    /**
     * Automatically resolve a class's dependencies using reflection.
     *
     * @param string $class
     * @return mixed
     * @throws Exception
     */
    protected function resolve(string $class)
    {
        $reflector = new ReflectionClass($class);

        // Check if the class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        // If the class has no constructor, create an instance without any arguments
        if (is_null($constructor)) {
            return new $class;
        }

        // Otherwise, resolve all dependencies recursively
        $parameters = $constructor->getParameters();
        $dependencies = array_map(function ($parameter) {
            return $this->make($parameter->getType()->getName());
        }, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }
}
