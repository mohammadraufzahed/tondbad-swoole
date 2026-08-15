<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Grpc;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Grpc\Request;
use TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld\GreeterGrpcAdapter;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;
use TondbadSwoole\Tests\Fixtures\Grpc\Services\GreeterImpl;

it('binds a generated adapter and invokes the implementation', function () {
    $container = new Container();
    $container->singleton(GreeterImpl::class, fn () => new GreeterImpl());

    /** @var GreeterGrpcAdapter $adapter */
    $adapter = $container->make(GreeterGrpcAdapter::class);
    $definition = $adapter->bindService();

    expect($definition->name)->toBe('tondbad.test.helloworld.Greeter');

    $method = $definition->getMethod('SayHello');
    expect($method)->not->toBeNull();

    $request = new HelloRequest();
    $request->setName('World');

    $grpcRequest = new Request(
        $definition->name,
        'SayHello',
        $request,
        new \TondbadSwoole\Grpc\Metadata(),
        new \TondbadSwoole\Grpc\Context(),
    );

    /** @var HelloReply $reply */
    $reply = ($method->handler)($grpcRequest);

    expect($reply)->toBeInstanceOf(HelloReply::class);
    expect($reply->getMessage())->toBe('Hello, World');
});
