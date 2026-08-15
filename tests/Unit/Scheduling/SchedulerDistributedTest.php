<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Queue\Jobs\ScheduledJob;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Scheduling\Schedule;
use TondbadSwoole\Scheduling\ScheduleRegistry;
use TondbadSwoole\Scheduling\Scheduler;
use TondbadSwoole\Scheduling\SchedulerWorker;
use TondbadSwoole\Scheduling\Stores\MemoryScheduleStore;

beforeEach(function () {
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');
    $this->container = $this->app->container;
});

it('runs a scheduled closure once when two workers race for the same minute', function () {
    $ran = 0;
    $store = new MemoryScheduleStore();
    $registry = new ScheduleRegistry();

    $this->container->bind(ScheduleRegistry::class, static fn () => $registry);

    $scheduler = new Scheduler($store, $registry, $this->container, $this->app->basePath());
    $schedule = new Schedule($scheduler, $this->container, $this->app->basePath(), $registry);

    $schedule
        ->call(function () use (&$ran) {
            $ran++;
        })
        ->everyMinute();

    $worker1 = new SchedulerWorker($scheduler, null, 'node-1');
    $worker2 = new SchedulerWorker($scheduler, null, 'node-2');

    $now = new DateTimeImmutable();

    $c1 = $worker1->tick($now);
    $c2 = $worker2->tick($now);

    expect($c1)->toBe(1);
    expect($c2)->toBe(0);
    expect($ran)->toBe(1);
});

it('dispatches a ScheduledJob for distributed non-queue tasks', function () {
    $store = new MemoryScheduleStore();
    $registry = new ScheduleRegistry();

    $this->container->bind(ScheduleRegistry::class, static fn () => $registry);

    $scheduler = new Scheduler($store, $registry, $this->container, $this->app->basePath());
    $schedule = new Schedule($scheduler, $this->container, $this->app->basePath(), $registry);
    $ran = false;

    $schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->everyMinute();

    $worker = new SchedulerWorker($scheduler, null, 'node-1');
    $worker->tick(new DateTimeImmutable());

    expect($ran)->toBeTrue();
});

it('recovers stale locks before ticking', function () {
    $store = new MemoryScheduleStore();
    $registry = new ScheduleRegistry();

    $this->container->bind(ScheduleRegistry::class, static fn () => $registry);

    $scheduler = new Scheduler($store, $registry, $this->container, $this->app->basePath());
    $schedule = new Schedule($scheduler, $this->container, $this->app->basePath(), $registry);
    $ran = false;

    $event = $schedule
        ->call(function () use (&$ran) {
            $ran = true;
        })
        ->everyMinute();

    $now = new DateTimeImmutable();

    $store->claim(
        $event->getDefinition()->id,
        'node-1',
        $now->format('YmdHi'),
        $now->modify('-1 second'),
    );

    $scheduler->recoverLocks($now);

    $worker = new SchedulerWorker($scheduler, null, 'node-2');
    $worker->tick($now);

    expect($ran)->toBeTrue();
});

it('pauses and resumes a schedule through the scheduler', function () {
    $store = new MemoryScheduleStore();
    $registry = new ScheduleRegistry();

    $scheduler = new Scheduler($store, $registry, $this->container, $this->app->basePath());
    $schedule = new Schedule($scheduler, $this->container, $this->app->basePath(), $registry);

    $event = $schedule->call(fn () => null)->everyMinute()->description('pauseable');

    $scheduler->pause($event->getDefinition()->id);

    expect($scheduler->due(new DateTimeImmutable()))->toHaveCount(0);

    $scheduler->resume($event->getDefinition()->id);

    expect($scheduler->due(new DateTimeImmutable()))->toHaveCount(1);
});

it('manually triggers a scheduled task', function () {
    $store = new MemoryScheduleStore();
    $registry = new ScheduleRegistry();

    $scheduler = new Scheduler($store, $registry, $this->container, $this->app->basePath());
    $schedule = new Schedule($scheduler, $this->container, $this->app->basePath(), $registry);
    $ran = false;

    $event = $schedule->call(function () use (&$ran) {
        $ran = true;
    })->description('manual');

    expect($scheduler->trigger($event->getDefinition()->id))->toBeTrue();
    expect($ran)->toBeTrue();
});
