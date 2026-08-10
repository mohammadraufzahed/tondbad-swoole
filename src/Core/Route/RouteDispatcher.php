<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Throwable;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Pipeline;

class RouteDispatcher
{
    /**
     * @param array<int, mixed> $middlewares
     */
    public function __construct(
        private readonly RouteRegistrar $registrar,
        private readonly HandlerInvoker $invoker,
        private readonly ErrorHandler $errorHandler,
        private readonly Container $container,
        private readonly array $middlewares = []
    ) {
    }

    public function dispatch(Request $request, Response $response): void
    {
        try {
            $context = new HttpContext($request, $response);
            $pipeline = new Pipeline($this->container);

            $pipeline
                ->send($context)
                ->through($this->preparePipes($this->middlewares))
                ->then(function (HttpContext $context): void {
                    $this->handleRequest($context);
                });
        } catch (Throwable $e) {
            $this->errorHandler->handle($e, $response);
        }
    }

    private function handleRequest(HttpContext $context): void
    {
        $httpMethod = strtoupper($context->request->server['request_method']);
        $uri = $context->request->server['request_uri'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $this->registrar->getDispatcher()->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $context->response->status(404);
                $context->response->end('404 Not Found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $context->response->status(405);
                $context->response->end('405 Method Not Allowed');
                break;
            case Dispatcher::FOUND:
                $handler = $this->registrar->getHandler((int) $routeInfo[1]);
                $vars = $routeInfo[2];
                $this->invokeHandler($handler, $context, $vars);
                break;
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function invokeHandler(array|callable $handler, HttpContext $context, array $vars): void
    {
        $this->invoker->invoke($handler, $context->request, $context->response, $vars);
    }

    /**
     * @param array<int, mixed> $middlewares
     * @return array<int, mixed>
     */
    private function preparePipes(array $middlewares): array
    {
        return array_map(function (mixed $pipe): mixed {
            if ($pipe instanceof MiddlewareInterface) {
                return $this->wrapMiddleware($pipe);
            }

            if (is_string($pipe) && class_exists($pipe) && is_subclass_of($pipe, MiddlewareInterface::class)) {
                return $this->wrapMiddleware($this->container->make($pipe));
            }

            return $pipe;
        }, $middlewares);
    }

    private function wrapMiddleware(MiddlewareInterface $middleware): \Closure
    {
        return function (HttpContext $context, \Closure $next) use ($middleware): void {
            $middleware->process(
                $context->request,
                $context->response,
                function (Request $request, Response $response) use ($context, $next): void {
                    $context->request = $request;
                    $context->response = $response;

                    $next($context);
                }
            );
        };
    }
}
