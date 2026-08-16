<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TondbadSwoole\Grpc\Channel;
use TondbadSwoole\Grpc\Health\V1\HealthCheckRequest;
use TondbadSwoole\Grpc\Health\V1\HealthCheckResponse;
use TondbadSwoole\Grpc\Health\V1\HealthCheckResponse\ServingStatus;
use TondbadSwoole\Grpc\Reflection\V1alpha\ServerReflectionRequest;
use TondbadSwoole\Grpc\Reflection\V1alpha\ServerReflectionResponse;

$port = (int) ($argv[1] ?? 19510);

$ok = true;

go(function () use ($port, &$ok) {
    try {
        $channel = new Channel('127.0.0.1', $port);

        // Health check
        $healthReq = new HealthCheckRequest();
        $healthReq->setService('');

        $healthResp = $channel->invoke('/grpc.health.v1.Health/Check', $healthReq, HealthCheckResponse::class);

        if ($healthResp->getStatus() !== ServingStatus::SERVING) {
            echo 'ERR health status: ' . $healthResp->getStatus() . "\n";
            $ok = false;

            return;
        }

        // Reflection: list services
        $stream = $channel->clientStream('/grpc.reflection.v1alpha.ServerReflection/ServerReflectionInfo', ServerReflectionResponse::class);

        $req = new ServerReflectionRequest();
        $req->setListServices('*');
        $stream->send($req);

        $resp = $stream->closeWrite();

        if (!$resp instanceof ServerReflectionResponse) {
            echo "ERR no reflection response\n";
            $ok = false;

            return;
        }

        $list = $resp->getListServicesResponse();

        if ($list === null) {
            echo "ERR no list services response\n";
            $ok = false;

            return;
        }

        $serviceNames = [];
        foreach ($list->getService() as $service) {
            $serviceNames[] = $service->getName();
        }

        if (!in_array('grpc.health.v1.Health', $serviceNames, true)) {
            echo 'ERR missing health service in reflection: ' . json_encode($serviceNames) . "\n";
            $ok = false;

            return;
        }

        // Reflection: file containing symbol
        $stream2 = $channel->clientStream('/grpc.reflection.v1alpha.ServerReflection/ServerReflectionInfo', ServerReflectionResponse::class);

        $req2 = new ServerReflectionRequest();
        $req2->setFileContainingSymbol('grpc.health.v1.Health');
        $stream2->send($req2);

        $resp2 = $stream2->closeWrite();

        if ($resp2->getFileDescriptorResponse() === null || iterator_count($resp2->getFileDescriptorResponse()->getFileDescriptorProto()) === 0) {
            echo "ERR file containing symbol returned empty\n";
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
