<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

use RuntimeException;

/**
 * Runs a benchmark scenario in separate PHP processes and aggregates the samples.
 */
final class ForkedBenchmarkRunner
{
    public function __construct(
        private readonly string $runnerScript,
        private readonly ?TimeUnit $timeUnit = null,
        private readonly string $phpBinary = PHP_BINARY,
    ) {
    }

    public function run(Scenario $scenario): BenchmarkResult
    {
        $forks = max(1, $scenario->forks);

        if ($scenario->toExport() === null) {
            return $this->runInProcess($scenario, $forks);
        }

        $results = [];

        for ($i = 0; $i < $forks; $i++) {
            $results[] = $this->runInFork($scenario);
        }

        return $this->aggregate($results, $scenario);
    }

    private function runInFork(Scenario $scenario): BenchmarkResult
    {
        $payload = [
            'autoload' => $this->autoloadPath(),
            'scenario' => $scenario->toExport(),
        ];

        $json = base64_encode(json_encode($payload));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([$this->phpBinary, $this->runnerScript, $json], $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start benchmark fork.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0 || $output === false || $output === '') {
            throw new RuntimeException('Benchmark fork failed: ' . ($error ?: 'unknown error'));
        }

        $data = json_decode($output, true);

        if (!is_array($data)) {
            throw new RuntimeException('Benchmark fork returned invalid JSON: ' . $output);
        }

        return BenchmarkResult::fromArray($data);
    }

    private function runInProcess(Scenario $scenario, int $forks): BenchmarkResult
    {
        $runner = new BenchmarkRunner($this->timeUnit);
        $results = [];

        for ($i = 0; $i < $forks; $i++) {
            $results[] = $runner->run($scenario);
        }

        return $this->aggregate($results, $scenario);
    }

    /**
     * @param list<BenchmarkResult> $results
     */
    private function aggregate(array $results, Scenario $scenario): BenchmarkResult
    {
        $samples = [];
        $memorySamples = [];

        foreach ($results as $result) {
            $samples = [...$samples, ...$result->samples];
            $memorySamples = [...$memorySamples, ...$result->memorySamples];
        }

        return BenchmarkResult::fromSamples(
            name: $scenario->name,
            group: $scenario->group ?? '',
            mode: $scenario->mode,
            timeUnit: $this->timeUnit ?? $scenario->timeUnit,
            invocations: $scenario->invocations,
            forks: $scenario->forks,
            samples: $samples,
            memorySamples: $memorySamples,
            metadata: ['warmup' => $scenario->warmup],
        );
    }

    private function autoloadPath(): string
    {
        $candidates = [
            dirname($this->runnerScript) . '/../vendor/autoload.php',
            dirname($this->runnerScript) . '/../../../autoload.php',
            getcwd() . '/vendor/autoload.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to locate vendor/autoload.php for benchmark fork.');
    }
}
