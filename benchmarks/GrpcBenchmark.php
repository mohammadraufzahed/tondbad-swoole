<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use OpenSwoole\GRPC\Constant;
use OpenSwoole\GRPC\Context as OpenSwooleContext;
use OpenSwoole\GRPC\Request as OpenSwooleRequest;
use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Grpc\Middleware\Dispatcher;
use TondbadSwoole\Grpc\ServiceRegistry;
use TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld\GreeterGrpcAdapter;
use TondbadSwoole\Tests\Fixtures\Grpc\Helloworld\HelloRequest;

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class GrpcBenchmark
{
    private Dispatcher $dispatcher;

    private OpenSwooleRequest $request;

    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        $container = new Container();
        $registry = new ServiceRegistry($container);
        $registry->add(GreeterGrpcAdapter::class);

        $this->dispatcher = new Dispatcher($container, []);

        $requestMessage = new HelloRequest();
        $requestMessage->setName('World');

        $workerContext = new OpenSwooleContext(['tondbad.grpc.registry' => $registry]);
        $context = new OpenSwooleContext([
            'WORKER_CONTEXT' => $workerContext,
            Constant::CONTENT_TYPE => 'application/grpc+proto',
        ]);

        $this->request = new OpenSwooleRequest(
            $context,
            '/tondbad.test.helloworld.Greeter',
            'SayHello',
            $requestMessage->serializeToString(),
        );
    }

    public function benchUnaryDispatch(Blackhole $bh): void
    {
        $handler = new class () implements \OpenSwoole\GRPC\RequestHandlerInterface {};

        $response = $this->dispatcher->process($this->request, $handler);

        $bh->consume($response->getContext()->getValue(Constant::GRPC_STATUS));
    }
}
