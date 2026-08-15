<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher;
use InvalidArgumentException;
use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Contracts\RouteInterface;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Middleware\ThrottleMiddleware;
use TondbadSwoole\Http\Request as HttpRequest;
use TondbadSwoole\Http\Response as HttpResponse;
use TondbadSwoole\Routing\ResourceRegistrar;
use TondbadSwoole\Routing\SignedUrl;
use TondbadSwoole\Validation\Schema;

class Route implements RouteInterface
{
    private RouteRegistrar $registrar;
    private RouteDispatcher $dispatcher;

    /**
     * @var list<string>
     */
    private array $groupPrefixStack = [''];

    /**
     * @var list<list<class-string>>
     */
    private array $groupMiddlewareStack = [[]];

    /**
     * @var list<string>
     */
    private array $groupNamePrefixStack = [''];

    /**
     * @var list<string>
     */
    private array $groupNamespaceStack = [''];

    /**
     * @var list<array<string, string>>
     */
    private array $groupPatternStack = [[]];

    /**
     * @var array<string, string>
     */
    private array $globalPatterns = [];

    /**
     * @var array<string, list<class-string>>
     */
    private array $middlewareGroups = [];

    /**
     * @var array<string, array{0: string|list<string>, 1: string}>
     */
    private array $namedRoutes = [];

    /**
     * @var array<int, array{0: string|list<string>, 1: string}>
     */
    private array $routeNameIndex = [];

    private ?SignedUrl $signedUrlGenerator = null;

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
        $databaseManager = $this->container->make(DatabaseManager::class);
        $eventDispatcher = $this->container->has(\TondbadSwoole\Events\Contracts\EventDispatcher::class)
            ? $this->container->make(\TondbadSwoole\Events\Contracts\EventDispatcher::class)
            : null;
        $this->dispatcher = new RouteDispatcher($this->registrar, $invoker, $errorHandler, $this->container, $this->context, $databaseManager, $middlewares, $eventDispatcher);
    }

    public function addRoute(
        string|array $method,
        string $path,
        array|callable $handler,
        array $middlewares = [],
        ?string $name = null
    ): RouteDefinition {
        $fullPath = $this->currentPrefix() . $path;
        $handler = $this->resolveNamespacedHandler($handler);
        $allMiddlewares = array_merge($this->currentMiddlewares(), $this->expandMiddlewares($middlewares));

        $id = $this->registrar->addRoute($method, $fullPath, $handler, $allMiddlewares);

        foreach (array_merge($this->currentPatterns(), $this->globalPatterns) as $parameter => $pattern) {
            $this->registrar->setConstraint($id, $parameter, $pattern);
        }

        $this->routeNameIndex[$id] = [$method, $fullPath];

        if ($name !== null) {
            $this->setName($id, $name);
        }

        return new RouteDefinition($this, $id);
    }

    public function get(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('GET', $path, $handler, $middlewares, $name);
    }

    public function post(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('POST', $path, $handler, $middlewares, $name);
    }

    public function put(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares, $name);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares, $name);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares, $name);
    }

    public function options(string $path, array|callable $handler, array $middlewares = [], ?string $name = null): RouteDefinition
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middlewares, $name);
    }

    /**
     * @param callable(Route): void $callback
     * @param list<class-string> $middlewares
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $this->pushGroup($prefix, $middlewares);
        $callback($this);
        $this->popGroup();
    }

    public function pushGroup(
        string $prefix = '',
        array $middlewares = [],
        string $namePrefix = '',
        string $namespace = '',
        array $patterns = []
    ): void {
        $this->groupPrefixStack[] = $this->buildPrefix($prefix);
        $this->groupMiddlewareStack[] = array_merge($this->currentMiddlewares(), $this->expandMiddlewares($middlewares));
        $this->groupNamePrefixStack[] = $this->currentNamePrefix() . $namePrefix;
        $this->groupNamespaceStack[] = $this->buildNamespace($namespace);
        $this->groupPatternStack[] = array_merge($this->currentPatterns(), $patterns);
    }

    public function popGroup(): void
    {
        if (count($this->groupPrefixStack) <= 1) {
            throw new InvalidArgumentException('Cannot pop the root route group.');
        }

        array_pop($this->groupPrefixStack);
        array_pop($this->groupMiddlewareStack);
        array_pop($this->groupNamePrefixStack);
        array_pop($this->groupNamespaceStack);
        array_pop($this->groupPatternStack);
    }

    public function prefix(string $prefix): GroupBuilder
    {
        return (new GroupBuilder($this))->prefix($prefix);
    }

    /**
     * @param list<class-string> $middlewares
     */
    public function middleware(array $middlewares): GroupBuilder
    {
        return (new GroupBuilder($this))->middleware($middlewares);
    }

    public function name(string $prefix): GroupBuilder
    {
        return (new GroupBuilder($this))->name($prefix);
    }

    public function namespace(string $namespace): GroupBuilder
    {
        return (new GroupBuilder($this))->namespace($namespace);
    }

    public function pattern(string $parameter, string $pattern): self
    {
        $this->globalPatterns[$parameter] = $pattern;

        return $this;
    }

    public function resource(string $name, string $controller, array $options = []): void
    {
        (new ResourceRegistrar())->register($this, $name, $controller, $options);
    }

    public function apiResource(string $name, string $controller, array $options = []): void
    {
        $options['api'] = true;
        $this->resource($name, $controller, $options);
    }

    public function redirect(string $from, string $to, int $status = 302, array $middlewares = []): RouteDefinition
    {
        return $this->addRoute(['GET', 'HEAD'], $from, function (HttpRequest $request, HttpResponse $response) use ($to, $status): void {
            $response->redirect($to, $status);
        }, $middlewares);
    }

    public function fallback(array|callable $handler, array $middlewares = []): RouteDefinition
    {
        $definition = $this->addRoute(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
            '/{path:.*}',
            $handler,
            $middlewares
        );

        $this->registrar->setFallbackId($definition->getId());

        return $definition;
    }

    public function setConstraint(int $id, string $parameter, string $pattern): void
    {
        $this->registrar->setConstraint($id, $parameter, $pattern);
    }

    public function setSchema(int $id, string $parameter, Schema $schema): void
    {
        $this->registrar->setSchema($id, $parameter, $schema);
    }

    public function setName(int $id, string $name): void
    {
        if (!isset($this->routeNameIndex[$id])) {
            throw new InvalidArgumentException("Route id {$id} does not exist.");
        }

        $fullName = $this->currentNamePrefix() . $name;
        $this->namedRoutes[$fullName] = $this->routeNameIndex[$id];
    }

    public function setMiddleware(int $id, array $middlewares): void
    {
        $this->registrar->addMiddlewares($id, $this->expandMiddlewares($middlewares));
    }

    /**
     * @param list<\TondbadSwoole\Routing\Contracts\Guard|class-string<\TondbadSwoole\Routing\Contracts\Guard>> $guards
     */
    public function setGuards(int $id, array $guards): void
    {
        $this->registrar->setGuards($id, $guards);
    }

    /**
     * @param list<class-string> $middlewares
     */
    public function middlewareGroup(string $name, array $middlewares): self
    {
        $this->middlewareGroups[$name] = $middlewares;

        return $this;
    }

    /**
     * @param list<class-string|MiddlewareInterface|string> $middlewares
     * @return list<class-string|MiddlewareInterface>
     */
    public function expandMiddlewares(array $middlewares): array
    {
        $expanded = [];
        $queue = array_values($middlewares);

        while ($queue !== []) {
            $middleware = array_shift($queue);

            if (is_string($middleware) && isset($this->middlewareGroups[$middleware])) {
                array_unshift($queue, ...array_values($this->middlewareGroups[$middleware]));

                continue;
            }

            if (is_string($middleware) && str_starts_with($middleware, 'throttle')) {
                $expanded[] = $this->parseThrottleMiddleware($middleware);

                continue;
            }

            $expanded[] = $middleware;
        }

        return $expanded;
    }

    private function parseThrottleMiddleware(string $middleware): ThrottleMiddleware
    {
        $parts = explode(':', $middleware, 2);
        $max = 60;
        $window = 60;

        if (isset($parts[1])) {
            $values = array_map('intval', explode(',', $parts[1]));

            if (isset($values[0]) && $values[0] > 0) {
                $max = $values[0];
            }

            if (isset($values[1]) && $values[1] > 0) {
                $window = $values[1];
            }
        }

        return new ThrottleMiddleware($max, $window);
    }

    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function url(string $name, array $params = [], bool $relative = true): string
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Route named '{$name}' is not defined.");
        }

        $method = $this->namedRoutes[$name][0];
        $path = $this->namedRoutes[$name][1];
        $usedKeys = [];

        $path = preg_replace_callback('/\[([^\]]*)\]/', function (array $matches) use ($params, &$usedKeys): string {
            $segment = $matches[1];

            if (preg_match_all('/\{([^{}:]+)(?::[^\}]*)?\}/', $segment, $keys)) {
                foreach ($keys[1] as $key) {
                    if (!array_key_exists($key, $params)) {
                        return '';
                    }
                    $usedKeys[] = $key;
                }
            }

            return $segment;
        }, $path);

        $path = preg_replace_callback('/\{([^{}:]+)(?::[^\}]*)?\}/', function (array $matches) use ($params, &$usedKeys): string {
            $key = $matches[1];
            $usedKeys[] = $key;

            return (string) ($params[$key] ?? '');
        }, $path);

        $query = [];

        foreach ($params as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, $usedKeys, true)) {
                continue;
            }

            $query[$key] = $value;
        }

        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        if ($relative) {
            return $path;
        }

        $scheme = $this->config->get('app.url_scheme', 'http');
        $host = $this->config->get('app.url_host', 'localhost');

        return "{$scheme}://{$host}{$path}";
    }

    public function signedUrl(string $name, array $params = [], ?DateTimeInterface $expires = null, bool $relative = true): string
    {
        return $this->signedUrlGenerator()->make($this->url($name, $params, $relative), $expires);
    }

    public function temporarySignedUrl(string $name, DateTimeInterface $expires, array $params = [], bool $relative = true): string
    {
        return $this->signedUrl($name, $params, $expires, $relative);
    }

    public function signatureValid(HttpRequest $request): bool
    {
        return $this->signedUrlGenerator()->validate($request->path(), $request->queries());
    }

    private function signedUrlGenerator(): SignedUrl
    {
        return $this->signedUrlGenerator ??= new SignedUrl((string) $this->config->get('app.key', ''));
    }

    /**
     * @param array<class-string> $classNames
     */
    public function registerAnnotatedRoutes(array $classNames): void
    {
        foreach ($classNames as $className) {
            $reflection = new \ReflectionClass($className);

            $controllerAttributes = $reflection->getAttributes(\TondbadSwoole\Routing\Attributes\Controller::class);
            $basePath = '';
            $controllerMiddlewares = [];

            if ($controllerAttributes !== []) {
                $controller = $controllerAttributes[0]->newInstance();
                $basePath = $controller->path();
                $controllerMiddlewares = $controller->middlewares();
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(\TondbadSwoole\Core\Route\Attributes\Endpoint::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $this->addRoute($instance->method, $this->combinePath($basePath, $instance->path), [$className, $method->getName()], $controllerMiddlewares);
                }

                foreach ($method->getAttributes() as $attribute) {
                    if (!is_subclass_of($attribute->getName(), \TondbadSwoole\Routing\Attributes\RouteMethod::class) && $attribute->getName() !== \TondbadSwoole\Routing\Attributes\RouteMethod::class) {
                        continue;
                    }

                    $instance = $attribute->newInstance();

                    if (!$instance instanceof \TondbadSwoole\Routing\Attributes\RouteMethod) {
                        continue;
                    }

                    $this->addRoute(
                        $instance->httpMethod(),
                        $this->combinePath($basePath, $instance->path()),
                        [$className, $method->getName()],
                        $controllerMiddlewares,
                        $instance->name()
                    );
                }
            }
        }
    }

    private function combinePath(string $base, string $path): string
    {
        if ($base === '' && $path === '') {
            return '/';
        }

        if ($base === '') {
            return '/' . ltrim($path, '/');
        }

        if ($path === '') {
            return '/' . ltrim($base, '/');
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
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

    public function getHandler(int $id): array|callable
    {
        return $this->registrar->getHandler($id);
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->registrar->getDispatcher();
    }

    public function getRouteDispatcher(): RouteDispatcher
    {
        return $this->dispatcher;
    }

    public function warmRouteCache(): void
    {
        $this->registrar->warmCache();
    }

    private function currentPrefix(): string
    {
        return $this->groupPrefixStack[count($this->groupPrefixStack) - 1] ?? '';
    }

    private function buildPrefix(string $prefix): string
    {
        if ($prefix === '') {
            return $this->currentPrefix();
        }

        $current = rtrim($this->currentPrefix(), '/');

        return $current . '/' . ltrim($prefix, '/');
    }

    /**
     * @return list<class-string>
     */
    private function currentMiddlewares(): array
    {
        return $this->groupMiddlewareStack[count($this->groupMiddlewareStack) - 1] ?? [];
    }

    private function currentNamePrefix(): string
    {
        return $this->groupNamePrefixStack[count($this->groupNamePrefixStack) - 1] ?? '';
    }

    private function currentNamespace(): string
    {
        return $this->groupNamespaceStack[count($this->groupNamespaceStack) - 1] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function currentPatterns(): array
    {
        return $this->groupPatternStack[count($this->groupPatternStack) - 1] ?? [];
    }

    private function buildNamespace(string $namespace): string
    {
        if ($namespace === '') {
            return $this->currentNamespace();
        }

        if (str_starts_with($namespace, '\\')) {
            return ltrim($namespace, '\\');
        }

        $current = $this->currentNamespace();

        if ($current === '') {
            return $namespace;
        }

        return $current . '\\' . $namespace;
    }

    private function resolveNamespacedHandler(array|callable $handler): array|callable
    {
        if (!is_array($handler) || count($handler) !== 2 || !is_string($handler[0])) {
            return $handler;
        }

        $namespace = $this->currentNamespace();

        if ($namespace === '') {
            return $handler;
        }

        $class = $handler[0];

        if (str_contains($class, '\\') || str_starts_with($class, '\\')) {
            return $handler;
        }

        return [$namespace . '\\' . $class, $handler[1]];
    }
}
