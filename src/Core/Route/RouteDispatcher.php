<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Throwable;

class RouteDispatcher
{
    public function __construct(
        private readonly RouteRegistrar $registrar,
        private readonly HandlerInvoker $invoker,
        private readonly ErrorHandler $errorHandler
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
                    $this->invoker->invoke($handler, $request, $response, $vars);
                    break;
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle($e, $response);
        }
    }
}
