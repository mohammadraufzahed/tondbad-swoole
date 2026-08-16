<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Health\V1;

use TondbadSwoole\Grpc\AttributeServiceAdapter;
use TondbadSwoole\Grpc\Attributes\AsGrpcService;
use TondbadSwoole\Grpc\Attributes\GrpcMethod;
use TondbadSwoole\Grpc\StreamReader;
use TondbadSwoole\Grpc\StreamWriter;

#[AsGrpcService('grpc.health.v1.Health', 'grpc.health.v1')]
class HealthService extends AttributeServiceAdapter
{
    #[GrpcMethod('Check', HealthCheckRequest::class, HealthCheckResponse::class)]
    public function check(HealthCheckRequest $request): HealthCheckResponse
    {
        $response = new HealthCheckResponse();
        $response->setStatus(HealthCheckRegistry::status($request->getService()));

        return $response;
    }

    #[GrpcMethod('Watch', HealthCheckRequest::class, HealthCheckResponse::class, clientStreaming: true, serverStreaming: true)]
    public function watch(StreamReader $stream, StreamWriter $writer): void
    {
        $request = $stream->recv();
        $service = $request instanceof HealthCheckRequest ? $request->getService() : '';

        $response = new HealthCheckResponse();
        $response->setStatus(HealthCheckRegistry::status($service));
        $writer->write($response);
    }
}
