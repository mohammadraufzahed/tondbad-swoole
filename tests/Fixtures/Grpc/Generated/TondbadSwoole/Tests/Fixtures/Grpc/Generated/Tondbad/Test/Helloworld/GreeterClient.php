<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld;

use TondbadSwoole\Grpc\Channel;
use TondbadSwoole\Grpc\Stream;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

class GreeterClient
{
    public function __construct(private readonly Channel $channel) {}

    public function sayHello(HelloRequest $request, array $metadata = []): HelloReply
    {
        return $this->channel->invoke('/tondbad.test.helloworld.Greeter/SayHello', $request, HelloReply::class, $metadata);
    }

    public function sayHelloStream(HelloRequest $request, array $metadata = []): Stream
    {
        return $this->channel->stream('/tondbad.test.helloworld.Greeter/SayHelloStream', $request, HelloReply::class, $metadata);
    }
}
