<?php

namespace TondbadSwoole\Core\Route;

use Exception;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Attributes\Endpoint;
use TondbadSwoole\Core\Route\Contracts\RouteInterface;

class Route implements RouteInterface
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
        'TRACE'
    ];

    protected static array $routes = [];
    protected Dispatcher $dispatcher;
    protected readonly ?Logger $logger;
    protected readonly Container $container;

    public function __construct()
    {
        $this->container = Container::create();
        $this->logger = $this->container->make(Logger::class);
    }

    public static function registerAnnotatedRoutes(array $classNames): void
    {
        foreach ($classNames as $className) {
            $reflection = new ReflectionClass($className);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Endpoint::class);

                foreach ($attributes as $attribute) {
                    $instance = $attribute->newInstance();
                    self::addRoute(
                        $instance->method,
                        $instance->path,
                        [$className, $method->getName()]
                    );
                }
            }
        }
    }

    public function dispatch(Request $request, Response $response): void
    {
        $httpMethod = strtoupper($request->server['request_method']);
        $uri = $request->server['request_uri'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeCollector = new RouteCollector(new RouteParser(), new DataGenerator());

        foreach (self::$routes as $route) {
            [$method, $path, $handler] = $route;
            $routeCollector->addRoute($method, $path, $handler);
        }

        $this->dispatcher = new Dispatcher($routeCollector->getData());
        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response->status(404);
                $response->end('404 Not Found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response->status(405);
                $response->end('405 Method Not Allowed');
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2]; // Extract route parameters

                // Call the handler, passing in request, response, and resolved dependencies
                $this->callHandler($handler, array_merge([$request, $response], $vars));
                break;
        }
    }

    public static function addRoute(string $method, string $path, callable $handler): void
    {
        if (!in_array($method, self::ALLOWED_METHODS)) {
            throw new Exception("$method method is not supported");
        }
        self::$routes[] = [$method, $path, $handler];
    }

    protected function callHandler(callable $handler, array $parameters): void
    {
        try {
            $reflection = new ReflectionFunction($handler);
            $dependencies = [];

            foreach ($reflection->getParameters() as $param) {
                $name = $param->getName();
                $type = $param->getType();
                if ($type && !$type->isBuiltin() && !in_array($name, ['request', 'response'])) {
                    $dependencies[] = $this->container->make($param->getType());
                } else {
                    $dependencies[] = array_shift($parameters);
                }
            }

            call_user_func_array($handler, $dependencies);
        } catch (Throwable $e) {
            $this->handleError($e, $dependencies[1]);
        }
    }

    protected function handleError(Throwable $e, Response $response): void
    {
        $this->logger?->error($e);

        $response->status(500);
        $response->end('500 Internal Server Error: ' . $e->getMessage());
    }
}