<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Grpc\Frame;
use TondbadSwoole\Grpc\Health\V1\HealthCheckRequest;

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class GrpcStreamingBenchmark
{
    private HealthCheckRequest $message;
    private string $payload;

    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        $this->message = new HealthCheckRequest();
        $this->message->setService('grpc.benchmark');
        $this->payload = Frame::encode($this->message);
    }

    public function benchFrameEncode(Blackhole $bh): void
    {
        $bh->consume(Frame::encode($this->message));
    }

    public function benchFrameDecode(Blackhole $bh): void
    {
        $bh->consume(iterator_to_array(Frame::decode($this->payload, HealthCheckRequest::class), false));
    }
}
