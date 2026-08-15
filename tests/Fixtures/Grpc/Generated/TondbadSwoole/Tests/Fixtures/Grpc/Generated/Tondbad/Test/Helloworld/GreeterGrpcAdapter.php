<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld;

use TondbadSwoole\Grpc\BindableService;
use TondbadSwoole\Grpc\MethodDescriptor;
use TondbadSwoole\Grpc\Request;
use TondbadSwoole\Grpc\ServiceDefinition;
use TondbadSwoole\Grpc\ServiceInvoker;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;
use TondbadSwoole\Tests\Fixtures\Grpc\Services\GreeterImpl;

class GreeterGrpcAdapter implements BindableService
{
    public function __construct(private readonly GreeterImpl $impl) {}

    public function bindService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'tondbad.test.helloworld.Greeter',
            package: 'tondbad.test.helloworld',
            methods: [
    new MethodDescriptor(
        name: 'SayHello',
        inputClass: HelloRequest::class,
        outputClass: HelloReply::class,
        handler: function (Request $request): HelloReply {
            return ServiceInvoker::invoke($this->impl, 'SayHello', $request);
        },
    ),
    new MethodDescriptor(
        name: 'SayHelloStream',
        inputClass: HelloRequest::class,
        outputClass: HelloReply::class,
        handler: function (Request $request): HelloReply {
            return ServiceInvoker::invoke($this->impl, 'SayHelloStream', $request);
        },
    ),
            ],
        );
    }
}
