<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\GRPC\GrpcHttpRequest;
use TondbadSwoole\GRPC\GrpcHttpResponse;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class RoutingBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        app()->routes()->get('/users/{id}', function (Request $request, Response $response): void {
            $response->end('ok');
        })->whereNumber('id');

        app()->routes()->warmRouteCache();
    }

    public function benchRouteDispatch(Blackhole $bh): void
    {
        $request = new GrpcHttpRequest('', ['request_method' => 'GET', 'request_uri' => '/users/42'], []);
        $response = new GrpcHttpResponse();

        app()->routes()->getRouteDispatcher()->dispatch($request, $response);

        $bh->consume($response->capturedStatus);
    }
}
