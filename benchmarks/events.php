<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\Dispatcher;
use TondbadSwoole\Events\Event;
use TondbadSwoole\Events\GenericEvent;

final class PingEvent extends Event
{
    public function __construct(public readonly int $id = 1) {}
}

$iterations = 100_000;
$container = new Container();
$container->singleton(EventDispatcher::class, fn () => new Dispatcher($container));
$dispatcher = $container->make(EventDispatcher::class);

$calls = 0;
$listener = function (PingEvent $event) use (&$calls): void {
    $calls++;
};

$results = [];

function benchmark(callable $fn, int $iterations): float
{
    // Warm up
    $fn();

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    return microtime(true) - $start;
}

function report(string $label, float $seconds, int $iterations, array &$results): void
{
    $us = $seconds * 1_000_000 / $iterations;
    $results[] = sprintf("%-40s %.4f s (%.3f μs/iter)", $label, $seconds, $us);
}

// 1. No listeners (DeadEvent overhead)
$fresh = new Dispatcher($container);
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('no listeners (DeadEvent)', $time, $iterations, $results);

// 2. One typed listener
$fresh = new Dispatcher($container);
$fresh->listen(PingEvent::class, $listener);
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('one typed listener', $time, $iterations, $results);

// 3. Five typed listeners with priorities
$fresh = new Dispatcher($container);
for ($p = 0; $p < 5; $p++) {
    $fresh->listen(PingEvent::class, $listener, priority: $p);
}
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('five typed listeners (priority)', $time, $iterations, $results);

// 4. Parent class listener (hierarchy dispatch)
$fresh = new Dispatcher($container);
$fresh->listen(Event::class, $listener);
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('parent class listener (Event::class)', $time, $iterations, $results);

// 5. String event with one listener
$fresh = new Dispatcher($container);
$fresh->listen('ping', $listener);
$time = benchmark(fn () => $fresh->dispatch('ping'), $iterations);
report('one string listener', $time, $iterations, $results);

// 6. Wildcard listener
$fresh = new Dispatcher($container);
$fresh->listen('ping.*', $listener);
$time = benchmark(fn () => $fresh->dispatch('ping.event'), $iterations);
report('wildcard listener (ping.* -> ping.event)', $time, $iterations, $results);

// 7. once listener (first dispatch)
$fresh = new Dispatcher($container);
$fresh->once(PingEvent::class, $listener);
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('once listener (first only)', $time, $iterations, $results);

// 8. until with early stop
$fresh = new Dispatcher($container);
$fresh->listen(PingEvent::class, fn () => null, priority: 10);
$fresh->listen(PingEvent::class, fn () => null, priority: 5);
$fresh->listen(PingEvent::class, fn () => 'stopped', priority: 0);
$fresh->listen(PingEvent::class, fn () => 'never', priority: -5);
$time = benchmark(fn () => $fresh->until(new PingEvent()), $iterations);
report('until (stops at 3rd listener)', $time, $iterations, $results);

// 9. stopPropagation
$fresh = new Dispatcher($container);
$fresh->listen(PingEvent::class, function (PingEvent $event) use (&$calls): void {
    $calls++;
    $event->stopPropagation();
});
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('stopPropagation after first', $time, $iterations, $results);

// 10. Exception isolation
$fresh = new Dispatcher($container);
$fresh->listen(PingEvent::class, function (): void {
    throw new RuntimeException('boom');
});
$fresh->listen(PingEvent::class, $listener);
$time = benchmark(fn () => $fresh->dispatch(new PingEvent()), $iterations);
report('exception isolation (one throws, one runs)', $time, $iterations, $results);

$peakMemory = memory_get_peak_usage(true) / 1024 / 1024;

printf("Event dispatcher benchmark (%d iterations)\n", $iterations);
echo implode("\n", $results), "\n";
printf("Peak memory: %.2f MB\n", $peakMemory);
