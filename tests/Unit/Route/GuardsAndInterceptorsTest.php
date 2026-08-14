<?php

declare(strict_types=1);

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\HandlerInvoker;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\Attributes\Controller;
use TondbadSwoole\Routing\Attributes\Guard;
use TondbadSwoole\Routing\Attributes\Interceptor;
use TondbadSwoole\Routing\Contracts\Guard as GuardContract;
use TondbadSwoole\Routing\Contracts\Interceptor as InterceptorContract;

class AllowGuard implements GuardContract
{
    public function can(Request $request): bool
    {
        return true;
    }
}

class DenyGuard implements GuardContract
{
    public function can(Request $request): bool
    {
        return false;
    }
}

class AppendInterceptor implements InterceptorContract
{
    public function intercept(Request $request, Response $response, callable $next): mixed
    {
        $request->getSwooleRequest()->get['intercepted'] = true;

        return $next();
    }
}

function makeInvoker(): HandlerInvoker
{
    return new HandlerInvoker(new Container());
}

function makeRequestResponse(): array
{
    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];

    return [new Request($swoole), new Response(new OpenSwoole\Http\Response())];
}

it('allows a request when guard returns true', function () {
    $controller = new #[Guard(AllowGuard::class)] class {
        public function index(): string
        {
            return 'ok';
        }
    };

    [$request, $response] = makeRequestResponse();
    makeInvoker()->invoke([get_class($controller), 'index'], $request, $response, []);

    expect(true)->toBeTrue();
});

it('denies a request when guard returns false', function () {
    $controller = new #[Guard(DenyGuard::class)] class {
        public function index(): string
        {
            return 'ok';
        }
    };

    [$request, $response] = makeRequestResponse();

    expect(fn () => makeInvoker()->invoke([get_class($controller), 'index'], $request, $response, []))
        ->toThrow(AuthorizationException::class);
});

it('applies guards declared on #[Controller]', function () {
    $controller = new #[Controller('/admin', guards: [DenyGuard::class])] class {
        public function index(): string
        {
            return 'ok';
        }
    };

    [$request, $response] = makeRequestResponse();

    expect(fn () => makeInvoker()->invoke([get_class($controller), 'index'], $request, $response, []))
        ->toThrow(AuthorizationException::class);
});

it('runs interceptors around the handler', function () {
    $controller = new #[Interceptor(AppendInterceptor::class)] class {
        public function index(Request $request): mixed
        {
            return $request->query('intercepted');
        }
    };

    [$request, $response] = makeRequestResponse();
    makeInvoker()->invoke([get_class($controller), 'index'], $request, $response, []);

    expect($request->query('intercepted'))->toBeTrue();
});
