<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use Throwable;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Pipeline;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

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

    public function dispatch(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $request = new Request($swooleRequest);
            $response = new Response($swooleResponse);

            $httpMethod = $request->method();
            $uri = $request->path();

            $routeInfo = $this->registrar->getDispatcher()->dispatch($httpMethod, $uri);

            switch ($routeInfo[0]) {
                case Dispatcher::NOT_FOUND:
                    $response->status(404)->end('404 Not Found');
                    break;
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $response->status(405)->end('405 Method Not Allowed');
                    break;
                case Dispatcher::FOUND:
                    $handlerId = (int) $routeInfo[1];
                    $vars = $routeInfo[2];
                    $this->dispatchRoute($request, $response, $handlerId, $vars);
                    break;
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle($e, $swooleResponse);
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function dispatchRoute(Request $request, Response $response, int $handlerId, array $vars): void
    {
        $handler = $this->registrar->getHandler($handlerId);
        $routeMiddlewares = $this->registrar->getMiddlewares($handlerId);

        $context = new HttpContext($request, $response);
        $pipeline = new Pipeline($this->container);

        $pipeline
            ->send($context)
            ->through($this->preparePipes(array_merge($this->middlewares, $routeMiddlewares)))
            ->then(function (HttpContext $context) use ($handler, $vars): void {
                $this->invokeHandler($handler, $context, $vars);
            });
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
