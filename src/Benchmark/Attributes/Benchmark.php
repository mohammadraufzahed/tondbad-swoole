<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Attributes;

use TondbadSwoole\Benchmark\Mode;
use TondbadSwoole\Benchmark\TimeUnit;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Benchmark
{
    public readonly Mode $mode;
    public readonly TimeUnit $timeUnit;

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $group = null,
        public readonly int $warmup = 5,
        public readonly int $iterations = 1000,
        public readonly int $invocations = 1,
        public readonly int $forks = 1,
        string|Mode $mode = 'avg',
        string|TimeUnit $timeUnit = 'us',
    ) {
        $this->mode = is_string($mode) ? Mode::fromString($mode) : $mode;
        $this->timeUnit = is_string($timeUnit) ? TimeUnit::fromString($timeUnit) : $timeUnit;
    }
}
