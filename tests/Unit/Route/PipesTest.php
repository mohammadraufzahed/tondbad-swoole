<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\HandlerInvoker;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Routing\Attributes\Param;
use TondbadSwoole\Routing\Attributes\Pipe;
use TondbadSwoole\Routing\Contracts\Pipe as PipeContract;

class DoubleIntPipe implements PipeContract
{
    public function transform(mixed $value, ?\ReflectionType $type = null): mixed
    {
        return (int) $value * 2;
    }
}

class AppendStringPipe implements PipeContract
{
    public function transform(mixed $value, ?\ReflectionType $type = null): mixed
    {
        return (string) $value . '_piped';
    }
}

function makeRequestResponseForPipes(): array
{
    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = ['q' => 'hello'];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];

    return [new Request($swoole), new Response(new OpenSwoole\Http\Response())];
}

it('applies a parameter pipe', function () {
    $invoker = new HandlerInvoker(new Container());
    [$request, $response] = makeRequestResponseForPipes();

    $captured = null;
    $handler = function (#[Param('id'), Pipe(DoubleIntPipe::class)] int $id) use (&$captured): void {
        $captured = $id;
    };

    $invoker->invoke($handler, $request, $response, ['id' => '5']);

    expect($captured)->toBe(10);
});

it('applies multiple parameter pipes in order', function () {
    $invoker = new HandlerInvoker(new Container());
    [$request, $response] = makeRequestResponseForPipes();

    $captured = null;
    $handler = function (#[Param('id'), Pipe(DoubleIntPipe::class), Pipe(AppendStringPipe::class)] string $id) use (&$captured): void {
        $captured = $id;
    };

    $invoker->invoke($handler, $request, $response, ['id' => '5']);

    expect($captured)->toBe('10_piped');
});
