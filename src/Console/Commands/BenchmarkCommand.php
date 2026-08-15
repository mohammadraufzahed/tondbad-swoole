<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use InvalidArgumentException;
use TondbadSwoole\Benchmark\BaselineStore;
use TondbadSwoole\Benchmark\BenchmarkComparator;
use TondbadSwoole\Benchmark\BenchmarkDiscovery;
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
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('benchmark', 'Run performance benchmarks.')]
class BenchmarkCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Benchmark file, class, or filter')
            ->addOption('warmup', null, InputOption::VALUE_OPTIONAL, 'Override warmup iterations', null, 'int')
            ->addOption('iterations', null, InputOption::VALUE_OPTIONAL, 'Override iterations', null, 'int')
            ->addOption('invocations', null, InputOption::VALUE_OPTIONAL, 'Override invocations per iteration', null, 'int')
            ->addOption('forks', null, InputOption::VALUE_OPTIONAL, 'Number of forks', null, 'int')
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Benchmark mode', null, 'string')
            ->addOption('timeUnit', null, InputOption::VALUE_OPTIONAL, 'Time unit', null, 'string')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (console, json, md)', null, 'string')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output file', null, 'string')
            ->addOption('save-baseline', null, InputOption::VALUE_OPTIONAL, 'Save baseline name', null, 'string')
            ->addOption('baseline', null, InputOption::VALUE_OPTIONAL, 'Compare against baseline', null, 'string')
            ->addOption('threshold', null, InputOption::VALUE_OPTIONAL, 'Regression threshold', null, 'float');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target');
        $options = $input->getOptions();
        $directories = $this->directories($target);

        try {
            $scenarios = $this->discoverScenarios($directories, $target, $options);
        } catch (\Throwable $e) {
            $output->error("Discovery failed: {$e->getMessage()}");

            return 1;
        }

        if ($scenarios === []) {
            $output->error('No benchmarks found.');

            return 1;
        }

        $results = $this->runScenarios($scenarios, $options, $output);

        if ($results === []) {
            $output->error('No benchmark results produced.');

            return 1;
        }

        $this->saveBaseline($options, $results);
        $this->report($options, $results);

        return $this->compareBaseline($options, $results);
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
    private function runScenarios(array $scenarios, array $options, OutputInterface $output): array
    {
        $results = [];
        $timeUnit = isset($options['timeUnit']) ? TimeUnit::fromString((string) $options['timeUnit']) : null;
        $forks = isset($options['forks']) ? (int) $options['forks'] : 1;

        $runner = $forks > 1
            ? new ForkedBenchmarkRunner($this->basePath . '/bin/benchmark-runner.php', $timeUnit)
            : new BenchmarkRunner($timeUnit);

        foreach ($scenarios as $scenario) {
            $output->writeln("Benchmarking: {$scenario->name}");
            $results[] = $runner->run($scenario);
        }

        return $results;
    }

    /**
     * @param list<string> $directories
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

        echo "Baseline saved to storage/benchmarks/" . $options['save-baseline'] . "\n";
    }

    /**
     * @param array<string, mixed> $options
     * @param list<BenchmarkResult> $results
     */
    private function report(array $options, array $results): void
    {
        $format = (string) ($options['format'] ?? 'console');
        $reporter = $this->reporter($format);
        $outputFile = isset($options['output']) ? (string) $options['output'] : null;

        $reporter->report($results, $outputFile);
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
