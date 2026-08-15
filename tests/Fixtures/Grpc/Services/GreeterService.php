<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Fixtures\Grpc\Services;

use TondbadSwoole\Grpc\BindableService;
use TondbadSwoole\Grpc\MethodDescriptor;
use TondbadSwoole\Grpc\Request;
use TondbadSwoole\Grpc\ServiceDefinition;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

final class GreeterService implements BindableService
{
    public function bindService(): ServiceDefinition
    {
        return new ServiceDefinition(
            'tondbad.test.helloworld.Greeter',
            [
                new MethodDescriptor(
                    'SayHello',
                    HelloRequest::class,
                    HelloReply::class,
                    handler: function (Request $request): HelloReply {
                        $reply = new HelloReply();
                        $reply->setMessage('Hello, ' . $request->message->getName());

                        return $reply;
                    },
                ),
            ],
            'tondbad.test.helloworld',
        );
    }
}
