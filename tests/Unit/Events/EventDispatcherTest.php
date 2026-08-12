<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\CallListenerJob;
use TondbadSwoole\Events\Dispatcher;
use TondbadSwoole\Events\Listener;
use TondbadSwoole\Events\ShouldQueue;
use TondbadSwoole\Tests\Unit\Events\FakeQueue;

beforeEach(function () {
    $this->container = new Container();
    $this->queue = new FakeQueue();
    $this->dispatcher = new Dispatcher($this->container, $this->queue);
});

it('dispatches string events to closures', function () {
    $received = null;

    $this->dispatcher->listen('user.created', function ($payload) use (&$received) {
        $received = $payload;
    });

    $this->dispatcher->dispatch('user.created', ['id' => 1]);

    expect($received)->toBe(['id' => 1]);
});

it('resolves listeners from class names with handle method', function () {
    $listener = new class() {
        public mixed $payload = null;

        public function handle($payload): void
        {
            $this->payload = $payload;
        }
    };

    $this->container->bind(get_class($listener), fn () => $listener);
    $this->dispatcher->listen('order.shipped', get_class($listener));
    $this->dispatcher->dispatch('order.shipped', 'payload');

    expect($listener->payload)->toBe('payload');
});

it('stops on first non-null response with until', function () {
    $this->dispatcher->listen('route.match', fn () => null);
    $this->dispatcher->listen('route.match', fn () => 'found');
    $this->dispatcher->listen('route.match', fn () => 'also');

    $result = $this->dispatcher->until('route.match', 'home');

    expect($result)->toBe('found');
});

it('passes object payloads to typed listener parameters', function () {
    $event = new \stdClass();
    $event->id = 2;

    $listener = new class() {
        public ?\stdClass $event = null;

        public function handle(\stdClass $event): void
        {
            $this->event = $event;
        }
    };

    $this->container->bind(get_class($listener), fn () => $listener);
    $this->dispatcher->listen('object.event', get_class($listener));
    $this->dispatcher->dispatch('object.event', $event);

    expect($listener->event)->toBe($event);
});

it('queues listeners that implement ShouldQueue', function () {
    $listener = new class() implements ShouldQueue {
        public function handle($payload): void
        {
            //
        }
    };

    $this->dispatcher->listen('queued.event', get_class($listener));
    $this->dispatcher->dispatch('queued.event', 'payload');

    expect($this->queue->size())->toBe(1);
    expect($this->queue->jobs[0])->toBeInstanceOf(CallListenerJob::class);
    expect($this->queue->jobs[0]->payload)->toBe('payload');
});

it('queues listeners with queued attribute', function () {
    $listener = new #[Listener(events: ['another.event'], queued: true)] class() {
        public function handle($payload): void
        {
            //
        }
    };

    $this->dispatcher->listen('another.event', get_class($listener));
    $this->dispatcher->dispatch('another.event', 'payload');

    expect($this->queue->size())->toBe(1);
});

it('forgets listeners', function () {
    $called = false;

    $this->dispatcher->listen('cleanup', function () use (&$called) {
        $called = true;
    });

    $this->dispatcher->forget('cleanup');
    $this->dispatcher->dispatch('cleanup');

    expect($called)->toBeFalse();
});

it('returns dispatcher events', function () {
    $this->dispatcher->listen('a', fn () => null);
    $this->dispatcher->listen('b', fn () => null);

    expect($this->dispatcher->getEvents())->toContain('a', 'b');
});
