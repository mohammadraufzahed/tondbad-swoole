<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Fixtures\Grpc\Services;

use TondbadSwoole\Grpc\AttributeServiceAdapter;
use TondbadSwoole\Grpc\Attributes\AsGrpcService;
use TondbadSwoole\Grpc\Attributes\GrpcMethod;
use TondbadSwoole\Grpc\StreamReader;
use TondbadSwoole\Grpc\StreamWriter;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

#[AsGrpcService('tondbad.test.streaming.Greeter')]
class StreamingGreeterService extends AttributeServiceAdapter
{
    #[GrpcMethod('CountDown', HelloRequest::class, HelloReply::class, serverStreaming: true)]
    public function countDown(HelloRequest $request, StreamWriter $writer): void
    {
        $start = (int) $request->getName();

        for ($i = $start; $i > 0; --$i) {
            $reply = new HelloReply();
            $reply->setMessage((string) $i);
            $writer->write($reply);
        }
    }

    #[GrpcMethod('SumNames', HelloRequest::class, HelloReply::class, clientStreaming: true)]
    public function sumNames(StreamReader $stream): HelloReply
    {
        $sum = '';

        while (($msg = $stream->recv()) !== null) {
            $sum .= $msg->getName();
        }

        $reply = new HelloReply();
        $reply->setMessage($sum);

        return $reply;
    }

    #[GrpcMethod('Echo', HelloRequest::class, HelloReply::class, clientStreaming: true, serverStreaming: true)]
    public function echo(StreamReader $stream, StreamWriter $writer): void
    {
        while (($msg = $stream->recv()) !== null) {
            $reply = new HelloReply();
            $reply->setMessage($msg->getName());
            $writer->write($reply);
        }
    }
}
