<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Exception;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector as FastRouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use ReflectionClass;
use TondbadSwoole\Core\Route\Attributes\Endpoint;

class RouteRegistrar
{
    private const ALLOWED_METHODS = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'OPTIONS',
        'HEAD',
        'CONNECT',
        'TRACE',
    ];

    /**
     * @var list<array{0: string, 1: string, 2: array|callable}>
     */
    private array $routes = [];

    private ?Dispatcher $dispatcher = null;

    public function addRoute(string $method, string $path, array|callable $handler): void
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new Exception("{$method} method is not supported");
        }

        $this->routes[] = [$method, $path, $handler];
        $this->dispatcher = null;
    }

    /**
     * @param array<class-string> $classNames
     */
    public function registerAnnotatedRoutes(array $classNames): void
    {
        foreach ($classNames as $className) {
            $reflection = new ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Endpoint::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $this->addRoute($instance->method, $instance->path, [$className, $method->getName()]);
                }
            }
        }
    }

    public function getDispatcher(): Dispatcher
    {
        if ($this->dispatcher !== null) {
            return $this->dispatcher;
        }

        $collector = new FastRouteCollector(new RouteParser(), new DataGenerator());

        foreach ($this->routes as [$method, $path, $handler]) {
            $collector->addRoute($method, $path, $handler);
        }

        $this->dispatcher = new Dispatcher($collector->processedRoutes());

        return $this->dispatcher;
    }

    /**
     * @return list<array{0: string, 1: string, 2: array|callable}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
