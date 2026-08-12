<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher;
use InvalidArgumentException;
use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Contracts\RouteInterface;

class Route implements RouteInterface
{
    private RouteRegistrar $registrar;
    private RouteDispatcher $dispatcher;

    private string $groupPrefix = '';

    /**
     * @var list<class-string>
     */
    private array $groupMiddlewares = [];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private array $namedRoutes = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly ContextInterface $context,
    ) {
        $routeCacheFile = $this->config->get('app.route_cache_file');
        $this->registrar = new RouteRegistrar($routeCacheFile);
        $invoker = new HandlerInvoker($this->container);
        $errorHandler = new ErrorHandler($this->config, $this->logger);
        $middlewares = $this->config->get('app.middlewares', []);
        $this->dispatcher = new RouteDispatcher($this->registrar, $invoker, $errorHandler, $this->container, $this->context, $middlewares);
    }

    public function addRoute(
        string $method,
        string $path,
        array|callable $handler,
        array $middlewares = [],
        ?string $name = null
    ): void {
        $fullPath = $this->groupPrefix . $path;
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $this->registrar->addRoute($method, $fullPath, $handler, $allMiddlewares);

        if ($name !== null) {
            $this->namedRoutes[$name] = [$method, $fullPath];
        }
    }

    public function get(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares, $name);
    }

    public function post(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares, $name);
    }

    public function put(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares, $name);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares, $name);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares, $name);
    }

    /**
     * @param callable(Route): void $callback
     * @param list<class-string> $middlewares
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix .= $prefix;
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function url(string $name, array $params = []): string
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Route named '{$name}' is not defined.");
        }

        $path = $this->namedRoutes[$name][1];

        $path = preg_replace_callback('/\[([^\]]*)\]/', function (array $matches) use ($params): string {
            $segment = $matches[1];

            if (preg_match_all('/\{([^}:]+)/', $segment, $keys)) {
                foreach ($keys[1] as $key) {
                    if (!array_key_exists($key, $params)) {
                        return '';
                    }
                }
            }

            return $segment;
        }, $path);

        return preg_replace_callback('/\{([^}:]+)(?::[^\}]+)?\}/', function (array $matches) use ($params): string {
            $key = $matches[1];

            return (string) ($params[$key] ?? '');
        }, $path);
    }

    /**
     * @param array<class-string> $classNames
     */
    public function registerAnnotatedRoutes(array $classNames): void
    {
        $this->registrar->registerAnnotatedRoutes($classNames);
    }

    public function dispatch(Request $request, Response $response): void
    {
        $this->dispatcher->dispatch($request, $response);
    }

    /**
     * @return list<array{0: string, 1: string, 2: array|callable, 3: list<class-string>}>
     */
    public function getRoutes(): array
    {
        return $this->registrar->getRoutes();
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->registrar->getDispatcher();
    }

    public function warmRouteCache(): void
    {
        $this->registrar->getDispatcher();
    }
}
