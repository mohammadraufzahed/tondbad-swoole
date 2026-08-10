<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Pipeline;
use TondbadSwoole\Tests\Unit\Fixtures\AppendPipe;

class PipelineTest extends TestCase
{
    public function test_then_return_runs_pipes_in_order(): void
    {
        $result = Pipeline::send('hello', new Container())
            ->through([
                new AppendPipe('-A'),
                new AppendPipe('-B'),
            ])
            ->thenReturn();

        $this->assertSame('hello-A-B', $result);
    }

    public function test_callable_pipes_receive_passable_and_next(): void
    {
        $result = Pipeline::send(10, new Container())
            ->through([
                fn(int $value, \Closure $next) => $next($value * 2),
                fn(int $value, \Closure $next) => $next($value + 1),
            ])
            ->thenReturn();

        // (10 * 2) + 1 = 21
        $this->assertSame(21, $result);
    }

    public function test_then_returns_destination_result(): void
    {
        $result = Pipeline::send('base', new Container())
            ->through([
                fn(string $value, \Closure $next) => $next($value . '-piped'),
            ])
            ->then(fn(string $value) => $value . '-done');

        $this->assertSame('base-piped-done', $result);
    }

    public function test_empty_pipeline_passes_value_unchanged(): void
    {
        $result = Pipeline::send('unchanged', new Container())
            ->through([])
            ->thenReturn();

        $this->assertSame('unchanged', $result);
    }
}
