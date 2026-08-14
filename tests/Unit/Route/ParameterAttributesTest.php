<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\HandlerInvoker;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\Attributes\Body;
use TondbadSwoole\Routing\Attributes\Header;
use TondbadSwoole\Routing\Attributes\Param;
use TondbadSwoole\Routing\Attributes\Query;
use TondbadSwoole\Routing\Attributes\Req;
use TondbadSwoole\Routing\Attributes\Res;

it('resolves #[Param] from route variables', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $captured = null;
    $handler = function (#[Param('id')] int $id) use (&$captured): void {
        $captured = $id;
    };

    $invoker->invoke($handler, $request, $response, ['id' => '42']);

    expect($captured)->toBe(42);
});

it('resolves #[Query] from request query', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = ['page' => '5'];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $captured = null;
    $handler = function (#[Query] int $page) use (&$captured): void {
        $captured = $page;
    };

    $invoker->invoke($handler, $request, $response, []);

    expect($captured)->toBe(5);
});

it('resolves #[Header] from request headers', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = ['x-request-id' => 'abc-123'];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $captured = null;
    $handler = function (#[Header('x-request-id')] string $requestId) use (&$captured): void {
        $captured = $requestId;
    };

    $invoker->invoke($handler, $request, $response, []);

    expect($captured)->toBe('abc-123');
});

it('resolves #[Body] as array', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = ['email' => 'test@example.com', 'name' => 'Test'];
    $swoole->server = [];
    $swoole->header = ['Content-Type' => 'application/x-www-form-urlencoded'];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $captured = null;
    $handler = function (#[Body] array $data) use (&$captured): void {
        $captured = $data;
    };

    $invoker->invoke($handler, $request, $response, []);

    expect($captured['email'])->toBe('test@example.com');
});

it('resolves #[Body] field from input', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = ['email' => 'test@example.com'];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $captured = null;
    $handler = function (#[Body('email')] string $email) use (&$captured): void {
        $captured = $email;
    };

    $invoker->invoke($handler, $request, $response, []);

    expect($captured)->toBe('test@example.com');
});

it('resolves #[Req] and #[Res]', function () {
    $invoker = new HandlerInvoker(new Container());

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());

    $capturedRequest = null;
    $capturedResponse = null;
    $handler = function (#[Req] Request $req, #[Res] Response $res) use (&$capturedRequest, &$capturedResponse): void {
        $capturedRequest = $req;
        $capturedResponse = $res;
    };

    $invoker->invoke($handler, $request, $response, []);

    expect($capturedRequest)->toBe($request);
    expect($capturedResponse)->toBe($response);
});
