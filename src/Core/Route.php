<?php

namespace TondbadSwoole\Core;

use FastRoute\RouteCollector;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Route
{
    protected array $routes = [];
    protected Dispatcher $dispatcher;

    public function __construct(
        protected readonly Container $container
    ) {
    }

    public function get(string $path, callable $handler)
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler)
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(Request $request, Response $response)
    {
        $httpMethod = strtoupper($request->server['request_method']);
        $uri = $request->server['request_uri'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeCollector = new RouteCollector(new RouteParser(), new DataGenerator());

        foreach ($this->routes as $route) {
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

    protected function callHandler(callable $handler, array $parameters)
    {
        $reflection = new \ReflectionFunction($handler);
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
    }
}
