<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

/**
 * Prevents dead-code elimination by consuming the benchmark result.
 *
 * The runner reads the public `$consumed` flag after each measured sample,
 * so the engine cannot prove the consume() call is unused.
 */
final class Blackhole
{
    public bool $consumed = false;

    public function consume(mixed $value): void
    {
        $this->consumed = $value !== null;
    }

    public function reset(): void
    {
        $this->consumed = false;
    }
}
