<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

/**
 * Fluent builder for one-off benchmarks.
 */
final class Benchmark
{
    private string $name;
    private ?string $group = null;
    private int $warmup = 5;
    private int $iterations = 1000;
    private int $invocations = 1;
    private int $forks = 1;
    private Mode $mode = Mode::AverageTime;
    private TimeUnit $timeUnit = TimeUnit::Microseconds;
    private ?\Closure $setup = null;
    private ?\Closure $teardown = null;
    private bool $coroutine = false;
    private int $workers = 1;
    private ?\Closure $benchmark = null;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function measure(string $name): self
    {
        return new self($name);
    }

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function warmup(int $warmup): self
    {
        $this->warmup = $warmup;

        return $this;
    }

    public function iterations(int $iterations): self
    {
        $this->iterations = $iterations;

        return $this;
    }

    public function invocations(int $invocations): self
    {
        $this->invocations = $invocations;

        return $this;
    }

    public function forks(int $forks): self
    {
        $this->forks = $forks;

        return $this;
    }

    public function mode(Mode|string $mode): self
    {
        $this->mode = is_string($mode) ? Mode::fromString($mode) : $mode;

        return $this;
    }

    public function timeUnit(TimeUnit|string $unit): self
    {
        $this->timeUnit = is_string($unit) ? TimeUnit::fromString($unit) : $unit;

        return $this;
    }

    public function setup(callable $setup): self
    {
        $this->setup = \Closure::fromCallable($setup);

        return $this;
    }

    public function teardown(callable $teardown): self
    {
        $this->teardown = \Closure::fromCallable($teardown);

        return $this;
    }

    public function coroutine(int $workers = 1): self
    {
        $this->coroutine = true;
        $this->workers = $workers;

        return $this;
    }

    public function run(callable $benchmark): BenchmarkResult
    {
        $scenario = new Scenario(
            name: $this->name,
            group: $this->group,
            warmup: $this->warmup,
            iterations: $this->iterations,
            invocations: $this->invocations,
            forks: $this->forks,
            mode: $this->mode,
            timeUnit: $this->timeUnit,
            benchmark: \Closure::fromCallable($benchmark),
            setupCallable: $this->setup,
            teardownCallable: $this->teardown,
            coroutine: $this->coroutine,
            workers: $this->workers,
        );

        return (new BenchmarkRunner())->run($scenario);
    }
}
