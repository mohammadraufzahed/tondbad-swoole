<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Exception;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector as FastRouteCollector;
use ReflectionClass;
use TondbadSwoole\Core\Route\Attributes\Endpoint;

use function FastRoute\cachedDispatcher;

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
     * @var list<array{0: string, 1: string, 2: int}>
     */
    private array $routes = [];

    /**
     * @var array<int, array|callable>
     */
    private array $handlers = [];

    /**
     * @var array<int, list<class-string>>
     */
    private array $middlewares = [];

    private ?Dispatcher $dispatcher = null;

    public function __construct(private readonly ?string $cacheFile = null)
    {
    }

    public function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): int
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new Exception("{$method} method is not supported");
        }

        $id = count($this->handlers);
        $this->handlers[$id] = $handler;
        $this->middlewares[$id] = $middlewares;
        $this->routes[] = [$method, $path, $id];
        $this->dispatcher = null;

        return $id;
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

        $this->ensureCacheDirectory();

        $this->dispatcher = cachedDispatcher(
            function (FastRouteCollector $r): void {
                foreach ($this->routes as [$method, $path, $id]) {
                    $r->addRoute($method, $path, $id);
                }
            },
            [
                'cacheFile' => $this->cacheFile,
                'cacheDisabled' => $this->cacheFile === null,
                'dispatcher' => Dispatcher::class,
                'dataGenerator' => DataGenerator::class,
            ]
        );

        return $this->dispatcher;
    }

    /**
     * @return array|callable
     */
    public function getHandler(int $id): array|callable
    {
        if (!array_key_exists($id, $this->handlers)) {
            throw new Exception("Route handler with id {$id} not found.");
        }

        return $this->handlers[$id];
    }

    /**
     * @return list<class-string>
     */
    public function getMiddlewares(int $id): array
    {
        return $this->middlewares[$id] ?? [];
    }

    private function ensureCacheDirectory(): void
    {
        if ($this->cacheFile === null) {
            return;
        }

        $directory = dirname($this->cacheFile);

        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new Exception("Unable to create route cache directory: {$directory}");
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: array|callable, 3: list<class-string>}>
     */
    public function getRoutes(): array
    {
        return array_map(
            fn(array $route) => [$route[0], $route[1], $this->handlers[$route[2]], $this->middlewares[$route[2]] ?? []],
            $this->routes
        );
    }
}
