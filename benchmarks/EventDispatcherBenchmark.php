<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Dispatcher;

#[Benchmark(warmup: 3, iterations: 10000, invocations: 10)]
class EventDispatcherBenchmark
{
    private Dispatcher $dispatcher;

    #[Setup]
    public function setUp(): void
    {
        $container = new Container();
        $this->dispatcher = new Dispatcher($container);
        $this->dispatcher->listen('ping', fn () => null);
    }

    public function benchDispatch(): void
    {
        $this->dispatcher->dispatch('ping');
    }
}
