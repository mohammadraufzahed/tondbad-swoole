<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

use RuntimeException;

/**
 * Reads and writes JSON baseline benchmark results.
 */
final class BaselineStore
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * @return array<string, BenchmarkResult>
     */
    public function load(string $name): array
    {
        $path = $this->path($name);

        if (!file_exists($path)) {
            throw new RuntimeException("Baseline not found: {$path}");
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read baseline: {$path}");
        }

        /** @var list<array<string, mixed>> $data */
        $data = json_decode($json, true) ?: [];
        $results = [];

        foreach ($data as $item) {
            $result = BenchmarkResult::fromArray($item);
            $results[$result->name] = $result;
        }

        return $results;
    }

    /**
     * @param array<string, BenchmarkResult>|list<BenchmarkResult> $results
     */
    public function save(string $name, array $results): void
    {
        $path = $this->path($name);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $list = array_values($results);

        file_put_contents(
            $path,
            json_encode(array_map(fn (BenchmarkResult $r) => $r->toArray(), $list), JSON_PRETTY_PRINT) . "\n",
        );
    }

    public function path(string $name): string
    {
        return $this->directory . '/' . ltrim($name, '/');
    }
}
