<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

/**
 * Immutable description of a single benchmark scenario.
 */
final class Scenario
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $group = null,
        public readonly array $params = [],
        public readonly int $warmup = 5,
        public readonly int $iterations = 1000,
        public readonly int $invocations = 1,
        public readonly int $forks = 1,
        public readonly Mode $mode = Mode::AverageTime,
        public readonly TimeUnit $timeUnit = TimeUnit::Microseconds,
        public readonly ?string $class = null,
        public readonly ?string $method = null,
        public readonly ?object $instance = null,
        public readonly ?\Closure $benchmark = null,
        public readonly ?string $setupMethod = null,
        public readonly ?string $teardownMethod = null,
        public readonly ?\Closure $setupCallable = null,
        public readonly ?\Closure $teardownCallable = null,
        public readonly ?string $file = null,
        public readonly bool $coroutine = false,
        public readonly int $workers = 1,
    ) {
    }

    /**
     * Export a serializable subset for forking. Class-based scenarios only.
     *
     * @return array<string, mixed>|null
     */
    public function toExport(): ?array
    {
        if ($this->benchmark !== null || $this->setupCallable !== null || $this->teardownCallable !== null) {
            return null;
        }

        return [
            'name' => $this->name,
            'group' => $this->group,
            'params' => $this->params,
            'warmup' => $this->warmup,
            'iterations' => $this->iterations,
            'invocations' => $this->invocations,
            'forks' => 1,
            'mode' => $this->mode->value,
            'timeUnit' => $this->timeUnit->value,
            'class' => $this->class,
            'method' => $this->method,
            'setupMethod' => $this->setupMethod,
            'teardownMethod' => $this->teardownMethod,
            'file' => $this->file,
            'coroutine' => $this->coroutine,
            'workers' => $this->workers,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromExport(array $data): self
    {
        return new self(
            name: $data['name'],
            group: $data['group'] ?? null,
            params: $data['params'] ?? [],
            warmup: $data['warmup'],
            iterations: $data['iterations'],
            invocations: $data['invocations'],
            forks: 1,
            mode: Mode::fromString($data['mode']),
            timeUnit: TimeUnit::fromString($data['timeUnit']),
            class: $data['class'] ?? null,
            method: $data['method'] ?? null,
            setupMethod: $data['setupMethod'] ?? null,
            teardownMethod: $data['teardownMethod'] ?? null,
            file: $data['file'] ?? null,
            coroutine: $data['coroutine'] ?? false,
            workers: $data['workers'] ?? 1,
        );
    }
}
