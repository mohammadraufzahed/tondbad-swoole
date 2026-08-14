<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Exception;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector as FastRouteCollector;
use ReflectionClass;
use TondbadSwoole\Routing\Contracts\Guard;
use TondbadSwoole\Validation\Schema;

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
     * @var list<array{0: string|list<string>, 1: string, 2: int}>
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

    /**
     * @var array<int, list<Guard|string>>
     */
    private array $guards = [];

    /**
     * @var array<int, array<string, string>>
     */
    private array $constraints = [];

    /**
     * @var array<int, array<string, Schema>>
     */
    private array $schemas = [];

    private ?Dispatcher $dispatcher = null;
    private ?int $fallbackId = null;

    public function __construct(private readonly ?string $cacheFile = null)
    {
    }

    /**
     * @param string|list<string> $method
     * @param list<class-string> $middlewares
     */
    public function addRoute(string|array $method, string $path, array|callable $handler, array $middlewares = []): int
    {
        $methods = is_array($method) ? $method : [$method];

        foreach ($methods as $m) {
            if (!in_array($m, self::ALLOWED_METHODS, true)) {
                throw new Exception("{$m} method is not supported");
            }
        }

        $id = count($this->handlers);
        $this->handlers[$id] = $handler;
        $this->middlewares[$id] = $middlewares;
        $this->routes[] = [$method, $path, $id];
        $this->dispatcher = null;

        return $id;
    }

    public function setConstraint(int $id, string $parameter, string $pattern): void
    {
        $this->constraints[$id][$parameter] = $pattern;
        $this->dispatcher = null;
    }

    public function setSchema(int $id, string $parameter, Schema $schema): void
    {
        $this->schemas[$id][$parameter] = $schema->lax();
        $this->constraints[$id][$parameter] = '[^/]+';
        $this->dispatcher = null;
    }

    public function getSchema(int $id, string $parameter): ?Schema
    {
        return $this->schemas[$id][$parameter] ?? null;
    }

    /**
     * @param list<class-string|\TondbadSwoole\Contracts\MiddlewareInterface> $middlewares
     */
    public function addMiddlewares(int $id, array $middlewares): void
    {
        if (!isset($this->middlewares[$id])) {
            $this->middlewares[$id] = [];
        }

        $this->middlewares[$id] = array_merge($this->middlewares[$id], $middlewares);
        $this->dispatcher = null;
    }

    /**
     * @param list<Guard|class-string<Guard>> $guards
     */
    public function setGuards(int $id, array $guards): void
    {
        $this->guards[$id] = $guards;
    }

    /**
     * @return list<Guard|class-string<Guard>>
     */
    public function getGuards(int $id): array
    {
        return $this->guards[$id] ?? [];
    }

    public function setFallbackId(int $id): void
    {
        $this->fallbackId = $id;
    }

    public function getFallbackId(): ?int
    {
        return $this->fallbackId;
    }

    public function warmCache(): void
    {
        $this->dispatcher = null;

        if ($this->cacheFile !== null && is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }

        $this->getDispatcher();
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
                    $r->addRoute($method, $this->buildPath($path, $this->constraints[$id] ?? []), $id);
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

    private function buildPath(string $path, array $constraints): string
    {
        if ($constraints === []) {
            return $path;
        }

        return preg_replace_callback(
            '/\{([^{}:]+)(?::[^{}]*)?\}/',
            function (array $matches) use ($constraints): string {
                $parameter = $matches[1];

                if (isset($constraints[$parameter])) {
                    return '{' . $parameter . ':' . $constraints[$parameter] . '}';
                }

                return '{' . $parameter . '}';
            },
            $path
        ) ?? $path;
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
            fn(array $route) => [
                is_array($route[0]) ? implode('|', $route[0]) : $route[0],
                $route[1],
                $this->handlers[$route[2]],
                $this->middlewares[$route[2]] ?? [],
            ],
            $this->routes
        );
    }
}
