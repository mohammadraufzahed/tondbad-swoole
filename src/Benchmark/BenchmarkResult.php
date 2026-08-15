<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

/**
 * Immutable statistical result for a benchmark scenario.
 */
final class BenchmarkResult
{
    /**
     * @param list<float> $samples nanoseconds per operation
     * @param list<int> $memorySamples bytes allocated per operation
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly string $group,
        public readonly Mode $mode,
        public readonly TimeUnit $timeUnit,
        public readonly int $iterations,
        public readonly int $invocations,
        public readonly int $forks,
        public readonly float $min,
        public readonly float $max,
        public readonly float $mean,
        public readonly float $median,
        public readonly float $stddev,
        public readonly float $p95,
        public readonly float $ci95Lower,
        public readonly float $ci95Upper,
        public readonly float $opsPerSecond,
        public readonly float $memoryPerOp,
        public readonly int $outliers,
        public readonly array $samples = [],
        public readonly array $memorySamples = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'group' => $this->group,
            'mode' => $this->mode->value,
            'timeUnit' => $this->timeUnit->value,
            'iterations' => $this->iterations,
            'invocations' => $this->invocations,
            'forks' => $this->forks,
            'min' => $this->timeUnit->fromNanos($this->min),
            'max' => $this->timeUnit->fromNanos($this->max),
            'mean' => $this->timeUnit->fromNanos($this->mean),
            'median' => $this->timeUnit->fromNanos($this->median),
            'stddev' => $this->timeUnit->fromNanos($this->stddev),
            'p95' => $this->timeUnit->fromNanos($this->p95),
            'ci95Lower' => $this->timeUnit->fromNanos($this->ci95Lower),
            'ci95Upper' => $this->timeUnit->fromNanos($this->ci95Upper),
            'opsPerSecond' => $this->opsPerSecond,
            'memoryPerOp' => $this->memoryPerOp,
            'outliers' => $this->outliers,
            'samples' => array_map(fn (float $ns) => $this->timeUnit->fromNanos($ns), $this->samples),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $unit = TimeUnit::fromString($data['timeUnit'] ?? 'us');

        return new self(
            name: $data['name'],
            group: $data['group'] ?? '',
            mode: Mode::fromString($data['mode'] ?? 'avg'),
            timeUnit: $unit,
            iterations: $data['iterations'],
            invocations: $data['invocations'] ?? 1,
            forks: $data['forks'] ?? 1,
            min: $unit->toNanos($data['min']),
            max: $unit->toNanos($data['max']),
            mean: $unit->toNanos($data['mean']),
            median: $unit->toNanos($data['median']),
            stddev: $unit->toNanos($data['stddev'] ?? 0.0),
            p95: $unit->toNanos($data['p95'] ?? 0.0),
            ci95Lower: $unit->toNanos($data['ci95Lower'] ?? 0.0),
            ci95Upper: $unit->toNanos($data['ci95Upper'] ?? 0.0),
            opsPerSecond: $data['opsPerSecond'] ?? 0.0,
            memoryPerOp: $data['memoryPerOp'] ?? 0.0,
            outliers: $data['outliers'] ?? 0,
            samples: array_map(fn (float $v) => $unit->toNanos($v), $data['samples'] ?? []),
            memorySamples: $data['memorySamples'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @param list<float> $samples
     * @param list<int> $memorySamples
     * @param array<string, mixed> $metadata
     */
    public static function fromSamples(
        string $name,
        string $group,
        Mode $mode,
        TimeUnit $timeUnit,
        int $invocations,
        int $forks,
        array $samples,
        array $memorySamples,
        array $metadata = [],
    ): self {
        $stats = Statistics::analyze($samples);
        $memoryPerOp = count($memorySamples) > 0 ? array_sum($memorySamples) / count($memorySamples) : 0.0;

        return new self(
            name: $name,
            group: $group,
            mode: $mode,
            timeUnit: $timeUnit,
            iterations: count($samples),
            invocations: $invocations,
            forks: $forks,
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
            metadata: $metadata,
        );
    }
}
