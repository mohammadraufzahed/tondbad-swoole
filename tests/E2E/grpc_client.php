<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use OpenSwoole\GRPC\Client;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

$port = (int) ($argv[1] ?? 19508);

go(function () use ($port) {
    try {
        $client = new Client('127.0.0.1', $port);
        $client->connect();

        $request = new HelloRequest();
        $request->setName('E2E');

        $streamId = $client->send('/tondbad.test.helloworld.Greeter/SayHello', $request, 'proto');
        [$data, $trailers] = $client->recv($streamId);

        $reply = new HelloReply();
        $reply->mergeFromString($data);

        $client->close();

        echo 'OK:' . $reply->getMessage() . "\n";
    } catch (\Throwable $e) {
        echo 'ERR:' . $e->getMessage() . "\n";
    }

    Swoole\Event::exit();
});

Swoole\Event::wait();
