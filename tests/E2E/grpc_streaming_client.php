<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TondbadSwoole\Grpc\Channel;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloReply;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

$port = (int) ($argv[1] ?? 19508);

function makeRequest(string $name): HelloRequest
{
    $request = new HelloRequest();
    $request->setName($name);

    return $request;
}

$ok = true;

go(function () use ($port, &$ok) {
    try {
        $channel = new Channel('127.0.0.1', $port);

        // Server streaming: CountDown 3 -> 3,2,1
        $stream = $channel->stream('/tondbad.test.streaming.Greeter/CountDown', makeRequest('3'), HelloReply::class);
        $values = [];

        while (($msg = $stream->recv()) !== null) {
            $values[] = $msg->getMessage();
        }

        if ($values !== ['3', '2', '1']) {
            echo 'ERR CountDown values: ' . json_encode($values) . "\n";
            $ok = false;

            return;
        }

        // Client streaming: SumNames a,b -> ab
        $clientStream = $channel->clientStream('/tondbad.test.streaming.Greeter/SumNames', HelloReply::class);
        $clientStream->send(makeRequest('a'));
        $clientStream->send(makeRequest('b'));
        $sum = $clientStream->close();

        if ($sum?->getMessage() !== 'ab') {
            echo 'ERR SumNames: ' . ($sum?->getMessage() ?? 'null') . "\n";
            $ok = false;

            return;
        }

        // Bidirectional: Echo a,b -> a,b
        $echo = $channel->clientStream('/tondbad.test.streaming.Greeter/Echo', HelloReply::class);
        $echo->send(makeRequest('x'));
        $echo->send(makeRequest('y'));

        $first = $echo->closeWrite();

        if ($first?->getMessage() !== 'x') {
            echo 'ERR Echo first: ' . ($first?->getMessage() ?? 'null') . "\n";
            $ok = false;

            return;
        }

        $second = $echo->recv();

        if ($second?->getMessage() !== 'y') {
            echo 'ERR Echo second: ' . ($second?->getMessage() ?? 'null') . "\n";
            $ok = false;

            return;
        }

        if ($echo->recv() !== null) {
            echo 'ERR Echo unexpected extra message' . "\n";
            $ok = false;

            return;
        }

        echo "OK\n";
    } catch (\Throwable $e) {
        echo 'ERR:' . $e->getMessage() . "\n";
        $ok = false;
    }
});

Swoole\Event::wait();

exit($ok ? 0 : 1);
