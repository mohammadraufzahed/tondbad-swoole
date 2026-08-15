<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Fixtures\Grpc\Services;

use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

final class GreeterImpl
{
    public function sayHello(HelloRequest $request): HelloReply
    {
        $reply = new HelloReply();
        $reply->setMessage('Hello, ' . $request->getName());

        return $reply;
    }
}
