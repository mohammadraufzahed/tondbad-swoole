<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Attributes\Teardown;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\View\Live\LiveComponent;
use TondbadSwoole\View\Live\LiveComponentManager;
use TondbadSwoole\View\Live\LivePatcher;
use TondbadSwoole\View\View;

class CounterBenchmarkComponent extends LiveComponent
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): View
    {
        return view('components.counter-bench', ['count' => $this->count]);
    }
}

#[Benchmark(warmup: 1, iterations: 100, invocations: 10)]
class ViewEngineBenchmark
{
    private LiveComponentManager $manager;
    private string $viewsPath;

    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        $basePath = getcwd() ?: __DIR__ . '/..';
        $this->viewsPath = $basePath . '/resources/views';

        if (!is_dir($this->viewsPath . '/components')) {
            mkdir($this->viewsPath . '/components', 0755, true);
        }

        file_put_contents($this->viewsPath . '/components/counter-bench.tond.php', <<<'VIEW'
<div data-t-live="counter-bench">
    <span id="count">{{ $count }}</span>
    <data-t-state></data-t-state>
</div>
VIEW
);

        $this->manager = app()?->container->make(LiveComponentManager::class);
        app()?->container->make(\TondbadSwoole\View\ViewManager::class)->registerComponent('counter-bench', CounterBenchmarkComponent::class);
    }

    #[Teardown]
    public function tearDown(): void
    {
        @unlink($this->viewsPath . '/components/counter-bench.tond.php');
    }

    public function benchRender(Blackhole $bh): void
    {
        $result = $this->manager->render('counter-bench');
        $bh->consume($result->html);
    }

    public function benchUpdate(Blackhole $bh): void
    {
        $first = $this->manager->render('counter-bench');
        $updated = $this->manager->update('counter-bench', [
            't:state' => $first->token,
            't:action' => 'increment',
        ]);
        $bh->consume($updated->html);
    }

    public function benchDiff(Blackhole $bh): void
    {
        $first = $this->manager->render('counter-bench');
        $updated = $this->manager->update('counter-bench', [
            't:state' => $first->token,
            't:action' => 'increment',
        ]);
        $bh->consume(LivePatcher::diff($first->html, $updated->html));
    }
}
