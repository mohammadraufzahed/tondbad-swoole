<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Monolog\Logger;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Contracts\RouteInterface;

class Route implements RouteInterface
{
    private RouteRegistrar $registrar;
    private RouteDispatcher $dispatcher;

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
        $routeCacheFile = $this->config->get('app.route_cache_file');
        $this->registrar = new RouteRegistrar($routeCacheFile);
        $invoker = new HandlerInvoker($this->container);
        $errorHandler = new ErrorHandler($this->config, $this->logger);
        $middlewares = $this->config->get('app.middlewares', []);
        $this->dispatcher = new RouteDispatcher($this->registrar, $invoker, $errorHandler, $this->container, $middlewares);
    }

    public function addRoute(string $method, string $path, array|callable $handler): void
    {
        $this->registrar->addRoute($method, $path, $handler);
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
     * @return list<array{0: string, 1: string, 2: array|callable}>
     */
    public function getRoutes(): array
    {
        return $this->registrar->getRoutes();
    }
}
