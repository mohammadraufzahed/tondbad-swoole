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
        $httpMethod = strtoupper($request->server['request_method']);
        $uri = $request->server['request_uri'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        try {
            $routeInfo = $this->registrar->getDispatcher()->dispatch($httpMethod, $uri);

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
                    $vars = $routeInfo[2];
                    $this->dispatchThroughPipeline($request, $response, $handler, $vars);
                    break;
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle($e, $response);
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private function dispatchThroughPipeline(Request $request, Response $response, array|callable $handler, array $vars): void
    {
        $context = new HttpContext($request, $response);
        $pipes = $this->preparePipes($this->middlewares);

        $pipeline = new Pipeline($this->container);

        $pipeline
            ->send($context)
            ->through($pipes)
            ->then(function (HttpContext $context) use ($handler, $vars): void {
                $this->invoker->invoke($handler, $context->request, $context->response, $vars);
            });
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
