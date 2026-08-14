<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use Throwable;
use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Pipeline;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\Contracts\Guard as RouteGuard;

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
        private readonly ContextInterface $context,
        private readonly DatabaseManager $databaseManager,
        private readonly array $middlewares = []
    ) {
    }

    public function dispatch(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $this->context->clear();

            $request = new Request($swooleRequest);
            $response = new Response($swooleResponse);

            $this->context->set('request', $request);
            $this->context->set('response', $response);

            $httpMethod = $request->method();
            $uri = $request->path();

            $routeInfo = $this->registrar->getDispatcher()->dispatch($httpMethod, $uri);

            switch ($routeInfo[0]) {
                case Dispatcher::NOT_FOUND:
                    $fallbackId = $this->registrar->getFallbackId();

                    if ($fallbackId !== null) {
                        $this->dispatchRoute($request, $response, $fallbackId, ['path' => ltrim($uri, '/')]);
                    } else {
                        $response->status(404)->end('404 Not Found');
                    }

                    break;
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $response->status(405)->end('405 Method Not Allowed');
                    break;
                case Dispatcher::FOUND:
                    $handlerId = (int) $routeInfo[1];
                    $vars = $routeInfo[2];

                    $validatedVars = $this->validateRouteParameters($request, $response, $handlerId, $vars);

                    if ($validatedVars === null) {
                        return;
                    }

                    $this->dispatchRoute($request, $response, $handlerId, $validatedVars);
                    break;
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle($e, $swooleResponse);
        } finally {
            $this->databaseManager->closeOldConnections();
            em()?->clear();
            $this->context->clear();
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

        Pipeline::send($context, $this->container)
            ->through($this->preparePipes(array_merge($this->middlewares, $routeMiddlewares)))
            ->then(function (HttpContext $context) use ($handler, $handlerId, $vars): void {
                $this->ensureGuards($context->request, $handlerId);
                $this->invokeHandler($handler, $context, $vars);
            });
    }

    private function ensureGuards(Request $request, int $handlerId): void
    {
        $guards = $this->registrar->getGuards($handlerId);

        foreach ($guards as $guard) {
            if (is_string($guard)) {
                $guard = $this->container->make($guard);
            }

            if (!$guard instanceof RouteGuard) {
                throw new \Exception('Route guard must implement Guard contract.');
            }

            if (!$guard->can($request)) {
                throw new AuthorizationException();
            }
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
     * @param array<string, string> $vars
     * @return array<string, mixed>|null
     */
    private function validateRouteParameters(Request $request, Response $response, int $handlerId, array $vars): ?array
    {
        foreach ($vars as $parameter => $value) {
            $schema = $this->registrar->getSchema($handlerId, $parameter);

            if ($schema === null) {
                continue;
            }

            $result = $schema->safeParse($value, $this->databaseManager);

            if (!$result->valid) {
                $response->status(404)->end('404 Not Found');

                return null;
            }

            $vars[$parameter] = $result->data;
        }

        return $vars;
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
