<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use InvalidArgumentException;
use TondbadSwoole\Benchmark\BenchmarkDiscovery;
use TondbadSwoole\Benchmark\BaselineStore;
use TondbadSwoole\Benchmark\BenchmarkComparator;
use TondbadSwoole\Benchmark\BenchmarkResult;
use TondbadSwoole\Benchmark\BenchmarkRunner;
use TondbadSwoole\Benchmark\ForkedBenchmarkRunner;
use TondbadSwoole\Benchmark\Mode;
use TondbadSwoole\Benchmark\Reporters\ConsoleReporter;
use TondbadSwoole\Benchmark\Reporters\JsonReporter;
use TondbadSwoole\Benchmark\Reporters\MarkdownReporter;
use TondbadSwoole\Benchmark\Reporters\Reporter;
use TondbadSwoole\Benchmark\Scenario;
use TondbadSwoole\Benchmark\TimeUnit;

class BenchmarkCommand extends Command
{
    public function getName(): string
    {
        return 'benchmark';
    }

    public function getDescription(): string
    {
        return 'Run performance benchmarks.';
    }

    public function run(array $args): int
    {
        $options = $this->parseOptions($args);

        $target = $options['_target'] ?? null;
        $directories = $this->directories($target);

        try {
            $scenarios = $this->discoverScenarios($directories, $target, $options);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Discovery failed: {$e->getMessage()}\n");

            return 1;
        }

        if ($scenarios === []) {
            fwrite(STDERR, "No benchmarks found.\n");

            return 1;
        }

        $results = $this->runScenarios($scenarios, $options);

        if ($results === []) {
            fwrite(STDERR, "No benchmark results produced.\n");

            return 1;
        }

        $this->saveBaseline($options, $results);
        $this->report($options, $results);
        $exitCode = $this->compareBaseline($options, $results);

        return $exitCode;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            $arg = (string) $arg;

            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                $options[$key] = $value === true ? true : $value;
            } else {
                $options['_target'] = $arg;
            }
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function directories(?string $target): array
    {
        if ($target !== null && is_dir($target)) {
            return [$target];
        }

        $dirs = [$this->basePath . '/benchmarks'];

        if (is_dir($this->basePath . '/app/Benchmarks')) {
            $dirs[] = $this->basePath . '/app/Benchmarks';
        }

        return $dirs;
    }

    /**
     * @param list<string> $directories
     * @param array<string, mixed> $options
     * @return list<Scenario>
     */
    private function discoverScenarios(array $directories, ?string $target, array $options): array
    {
        $discovery = new BenchmarkDiscovery($directories);
        $scenarios = $discovery->discover();

        if ($target !== null && !is_dir($target) && file_exists($target)) {
            require_once $target;

            $targetClass = $this->classNameFromFile($target);

            if ($targetClass === null) {
                throw new InvalidArgumentException("Unable to determine class name for {$target}");
            }

            $target = $targetClass;
        }

        if ($target !== null && !is_dir($target)) {
            $filter = $target;

            $scenarios = array_values(array_filter($scenarios, function (Scenario $scenario) use ($filter): bool {
                return str_contains($scenario->name, $filter) || str_contains($scenario->class ?? '', $filter);
            }));
        }

        return array_map(fn (Scenario $scenario) => $this->applyOverrides($scenario, $options), $scenarios);
    }

    private function classNameFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $name = basename($file, '.php');

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches) !== 1) {
            return $name;
        }

        return $matches[1] . '\\' . $name;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function applyOverrides(Scenario $scenario, array $options): Scenario
    {
        return new Scenario(
            name: $scenario->name,
            group: $scenario->group,
            params: $scenario->params,
            warmup: isset($options['warmup']) ? (int) $options['warmup'] : $scenario->warmup,
            iterations: isset($options['iterations']) ? (int) $options['iterations'] : $scenario->iterations,
            invocations: isset($options['invocations']) ? (int) $options['invocations'] : $scenario->invocations,
            forks: isset($options['forks']) ? (int) $options['forks'] : $scenario->forks,
            mode: isset($options['mode']) ? Mode::fromString((string) $options['mode']) : $scenario->mode,
            timeUnit: isset($options['timeUnit']) ? TimeUnit::fromString((string) $options['timeUnit']) : $scenario->timeUnit,
            class: $scenario->class,
            method: $scenario->method,
            instance: $scenario->instance,
            benchmark: $scenario->benchmark,
            setupMethod: $scenario->setupMethod,
            teardownMethod: $scenario->teardownMethod,
            setupCallable: $scenario->setupCallable,
            teardownCallable: $scenario->teardownCallable,
            file: $scenario->file,
            coroutine: $scenario->coroutine,
            workers: $scenario->workers,
        );
    }

    /**
     * @param list<Scenario> $scenarios
     * @param array<string, mixed> $options
     * @return list<BenchmarkResult>
     */
    private function runScenarios(array $scenarios, array $options): array
    {
        $results = [];
        $timeUnit = isset($options['timeUnit']) ? TimeUnit::fromString((string) $options['timeUnit']) : null;
        $forks = isset($options['forks']) ? (int) $options['forks'] : 1;

        $runner = $forks > 1
            ? new ForkedBenchmarkRunner($this->basePath . '/bin/benchmark-runner.php', $timeUnit)
            : new BenchmarkRunner($timeUnit);

        foreach ($scenarios as $scenario) {
            fwrite(STDOUT, "Benchmarking: {$scenario->name}\n");
            $results[] = $runner->run($scenario);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $options
     * @param list<BenchmarkResult> $results
     */
    private function saveBaseline(array $options, array $results): void
    {
        if (!isset($options['save-baseline'])) {
            return;
        }

        $store = new BaselineStore($this->basePath . '/storage/benchmarks');
        $store->save((string) $options['save-baseline'], $results);

        fwrite(STDOUT, "Baseline saved to storage/benchmarks/" . $options['save-baseline'] . "\n");
    }

    /**
     * @param array<string, mixed> $options
     * @param list<BenchmarkResult> $results
     */
    private function report(array $options, array $results): void
    {
        $format = (string) ($options['format'] ?? 'console');
        $reporter = $this->reporter($format);
        $output = isset($options['output']) ? (string) $options['output'] : null;

        $reporter->report($results, $output);
    }

    private function reporter(string $format): Reporter
    {
        return match ($format) {
            'json' => new JsonReporter(),
            'md', 'markdown' => new MarkdownReporter(),
            default => new ConsoleReporter(),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param list<BenchmarkResult> $results
     */
    private function compareBaseline(array $options, array $results): int
    {
        if (!isset($options['baseline'])) {
            return 0;
        }

        $store = new BaselineStore($this->basePath . '/storage/benchmarks');
        $baseline = $store->load((string) $options['baseline']);
        $threshold = isset($options['threshold']) ? (float) $options['threshold'] : 0.05;
        $comparator = new BenchmarkComparator($threshold);
        $regressions = $comparator->compare($baseline, $results);

        if ($regressions === []) {
            fwrite(STDOUT, "No regressions detected.\n");

            return 0;
        }

        fwrite(STDERR, "Performance regressions detected (threshold: " . ($threshold * 100) . "%):\n");

        foreach ($regressions as $regression) {
            fwrite(STDERR, sprintf(
                "  %s: %.2f%% slower (%.3f -> %.3f)\n",
                $regression['benchmark'],
                $regression['delta'] * 100,
                $regression['previous'],
                $regression['current'],
            ));
        }

        return 1;
    }
}
