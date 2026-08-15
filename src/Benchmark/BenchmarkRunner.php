<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

use OpenSwoole\Coroutine;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

/**
 * Runs a single benchmark scenario and returns a BenchmarkResult.
 */
class BenchmarkRunner
{
    private int $calibrationDummy = 0;
    private bool $consumedFlag = false;

    public function __construct(
        protected readonly ?TimeUnit $timeUnit = null,
    ) {
    }

    public function run(Scenario $scenario): BenchmarkResult
    {
        if ($scenario->coroutine && PHP_SAPI !== 'cli') {
            throw new RuntimeException('Coroutine benchmarks can only run in CLI mode.');
        }

        $run = fn () => $this->execute($scenario);

        if ($scenario->coroutine && extension_loaded('openswoole')) {
            $result = null;
            Coroutine::run(function () use ($run, &$result): void {
                $result = $run();
            });

            return $result;
        }

        return $run();
    }

    protected function execute(Scenario $scenario): BenchmarkResult
    {
        $instance = $this->resolveInstance($scenario);

        $this->applyParams($instance, $scenario->params);

        $benchmark = $this->resolveBenchmark($instance, $scenario);
        $setup = $this->resolveMethod($instance, $scenario->setupMethod);
        $teardown = $this->resolveMethod($instance, $scenario->teardownMethod);

        if ($scenario->setupCallable !== null) {
            ($scenario->setupCallable)();
        } else {
            $setup?->invoke($instance);
        }

        // Warm-up
        $bh = new Blackhole();
        for ($i = 0; $i < $scenario->warmup; $i++) {
            $this->invoke($benchmark, $bh);
            $bh->reset();
        }

        $needsBlackhole = $this->wantsBlackhole($benchmark);
        $samples = [];
        $memorySamples = [];

        // Calibrate loop overhead
        $calibrationNs = $this->calibrate($scenario->invocations);

        for ($i = 0; $i < $scenario->iterations; $i++) {
            $bh = new Blackhole();
            $memoryBefore = memory_get_usage(true);
            $start = hrtime(true);

            for ($j = 0; $j < $scenario->invocations; $j++) {
                $this->invoke($benchmark, $needsBlackhole ? $bh : null);
            }

            $end = hrtime(true);
            $memoryAfter = memory_get_usage(true);

            $totalNs = ($end - $start) - $calibrationNs;
            $perOpNs = $totalNs / $scenario->invocations;
            $samples[] = max(0.0, $perOpNs);

            $memoryDiff = ($memoryAfter - $memoryBefore) / $scenario->invocations;
            $memorySamples[] = max(0, (int) $memoryDiff);

            // Read the blackhole flag so the optimizer keeps consume() alive.
            if ($needsBlackhole) {
                $this->consumedFlag = $bh->consumed;
            }
        }

        if ($scenario->teardownCallable !== null) {
            ($scenario->teardownCallable)();
        } else {
            $teardown?->invoke($instance);
        }

        return $this->buildResult($scenario, $samples, $memorySamples);
    }

    protected function resolveInstance(Scenario $scenario): ?object
    {
        if ($scenario->instance !== null) {
            return $scenario->instance;
        }

        if ($scenario->class !== null) {
            $class = $scenario->class;

            if (!class_exists($class) && $scenario->file !== null && file_exists($scenario->file)) {
                require_once $scenario->file;
            }

            return new $class();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function applyParams(?object $instance, array $params): void
    {
        if ($instance === null) {
            return;
        }

        foreach ($params as $property => $value) {
            $reflection = new \ReflectionProperty($instance, $property);
            $reflection->setAccessible(true);
            $reflection->setValue($instance, $value);
        }
    }

    protected function resolveBenchmark(?object $instance, Scenario $scenario): callable
    {
        if ($scenario->benchmark !== null) {
            return $scenario->benchmark;
        }

        if ($instance !== null && $scenario->method !== null) {
            return [$instance, $scenario->method];
        }

        throw new RuntimeException('Scenario has no benchmark callable or class method.');
    }

    protected function resolveMethod(?object $instance, ?string $method): ?\ReflectionMethod
    {
        if ($instance === null || $method === null) {
            return null;
        }

        return new \ReflectionMethod($instance, $method);
    }

    protected function invoke(callable $benchmark, ?Blackhole $bh): mixed
    {
        $reflection = $this->reflectBenchmark($benchmark);

        if ($reflection->getNumberOfParameters() === 0) {
            return $benchmark();
        }

        $parameter = $reflection->getParameters()[0];
        $type = $parameter->getType();

        if ($type === null || ($type instanceof \ReflectionNamedType && $type->getName() === Blackhole::class)) {
            return $benchmark($bh);
        }

        // Non-blackhole typed parameter; call without arguments and let the engine error if needed.
        return $benchmark();
    }

    protected function wantsBlackhole(callable $benchmark): bool
    {
        $reflection = $this->reflectBenchmark($benchmark);

        if ($reflection->getNumberOfParameters() === 0) {
            return false;
        }

        $parameter = $reflection->getParameters()[0];
        $type = $parameter->getType();

        // Treat untyped or Blackhole-typed first parameters as blackhole consumers.
        return $type === null || ($type instanceof \ReflectionNamedType && $type->getName() === Blackhole::class);
    }

    private function reflectBenchmark(callable $benchmark): \ReflectionFunctionAbstract
    {
        if ($benchmark instanceof \Closure) {
            return new ReflectionFunction($benchmark);
        }

        if (is_array($benchmark)) {
            return new ReflectionMethod($benchmark[0], $benchmark[1]);
        }

        if (is_object($benchmark) && method_exists($benchmark, '__invoke')) {
            return new ReflectionMethod($benchmark, '__invoke');
        }

        return new ReflectionFunction($benchmark);
    }

    protected function calibrate(int $invocations): float
    {
        $dummy = 0;
        $start = hrtime(true);
        for ($i = 0; $i < $invocations; $i++) {
            $dummy ^= $i;
        }
        $end = hrtime(true);

        // Prevent the loop from being dead-code eliminated.
        $this->calibrationDummy = $dummy;

        return (float) ($end - $start);
    }

    /**
     * @param list<float> $samples
     * @param list<int> $memorySamples
     */
    protected function buildResult(Scenario $scenario, array $samples, array $memorySamples): BenchmarkResult
    {
        $stats = Statistics::analyze($samples);
        $memoryPerOp = count($memorySamples) > 0 ? array_sum($memorySamples) / count($memorySamples) : 0.0;
        $unit = $this->timeUnit ?? $scenario->timeUnit;

        return new BenchmarkResult(
            name: $scenario->name,
            group: $scenario->group ?? '',
            mode: $scenario->mode,
            timeUnit: $unit,
            iterations: $scenario->iterations,
            invocations: $scenario->invocations,
            forks: $scenario->forks,
            min: $stats['min'],
            max: $stats['max'],
            mean: $stats['mean'],
            median: $stats['median'],
            stddev: $stats['stddev'],
            p95: $stats['p95'],
            ci95Lower: $stats['ci95Lower'],
            ci95Upper: $stats['ci95Upper'],
            opsPerSecond: $stats['opsPerSecond'],
            memoryPerOp: $memoryPerOp,
            outliers: (int) $stats['outliers'],
            samples: $samples,
            memorySamples: $memorySamples,
            metadata: [
                'warmup' => $scenario->warmup,
            ],
        );
    }
}
