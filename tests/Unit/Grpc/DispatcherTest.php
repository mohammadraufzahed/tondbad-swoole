<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Grpc;

use OpenSwoole\GRPC\Constant;
use OpenSwoole\GRPC\Context as OpenSwooleContext;
use OpenSwoole\GRPC\Request as OpenSwooleRequest;
use OpenSwoole\GRPC\Response as OpenSwooleResponse;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Grpc\Middleware\Dispatcher;
use TondbadSwoole\Grpc\ServiceRegistry;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;
use TondbadSwoole\Tests\Fixtures\Grpc\Services\GreeterService;

it('dispatches a unary gRPC request and returns a serialized response', function () {
    $container = new Container();
    $registry = new ServiceRegistry($container);
    $registry->add(GreeterService::class);

    $dispatcher = new Dispatcher($container, []);

    $requestMessage = new HelloRequest();
    $requestMessage->setName('World');

    $workerContext = new OpenSwooleContext(['tondbad.grpc.registry' => $registry]);
    $context = new OpenSwooleContext([
        'WORKER_CONTEXT' => $workerContext,
        Constant::CONTENT_TYPE => 'application/grpc+proto',
    ]);

    $openSwooleRequest = new OpenSwooleRequest(
        $context,
        '/tondbad.test.helloworld.Greeter',
        'SayHello',
        $requestMessage->serializeToString(),
    );

    /** @var OpenSwooleResponse $response */
    $response = $dispatcher->process($openSwooleRequest, new class () implements \OpenSwoole\GRPC\RequestHandlerInterface {});

    expect($response)->toBeInstanceOf(OpenSwooleResponse::class);
    expect($response->getContext()->getValue(Constant::GRPC_STATUS))->toBe(0);
    expect($response->getContext()->getValue(Constant::GRPC_MESSAGE))->toBe('');

    $reply = new HelloReply();
    $reply->mergeFromString($response->getPayload());

    expect($reply->getMessage())->toBe('Hello, World');
});

it('returns unimplemented status for unknown service', function () {
    $container = new Container();
    $registry = new ServiceRegistry($container);
    $registry->add(GreeterService::class);

    $dispatcher = new Dispatcher($container, []);

    $workerContext = new OpenSwooleContext(['tondbad.grpc.registry' => $registry]);
    $context = new OpenSwooleContext([
        'WORKER_CONTEXT' => $workerContext,
        Constant::CONTENT_TYPE => 'application/grpc+proto',
    ]);

    $openSwooleRequest = new OpenSwooleRequest($context, '/UnknownService', 'SayHello', '');

    /** @var OpenSwooleResponse $response */
    $response = $dispatcher->process($openSwooleRequest, new class () implements \OpenSwoole\GRPC\RequestHandlerInterface {});

    expect($response->getContext()->getValue(Constant::GRPC_STATUS))->toBe(12);
});
