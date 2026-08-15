<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

class NoopConsoleCommand extends Command
{
    public function getName(): string
    {
        return 'noop';
    }

    public function getDescription(): string
    {
        return 'Noop command for benchmarking.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class ConsoleBenchmark
{
    private Application $application;

    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        $this->application = new Application(getcwd());
        $this->application->register(new NoopConsoleCommand(getcwd()));
    }

    public function benchRun(Blackhole $bh): void
    {
        $bh->consume($this->application->run(['tondbad', 'noop']));
    }
}
