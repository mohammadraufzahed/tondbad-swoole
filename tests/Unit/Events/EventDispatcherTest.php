<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\Contracts\QueueableEvent;
use TondbadSwoole\Events\DeadEvent;
use TondbadSwoole\Events\DispatchResult;
use TondbadSwoole\Events\Event;
use TondbadSwoole\Events\GenericEvent;
use TondbadSwoole\Events\Listener;
use TondbadSwoole\Tests\Unit\Events\FakeQueue;

beforeEach(function () {
    $this->container = new Container();
    $this->queue = new FakeQueue();
    $this->container->singleton(\TondbadSwoole\Queue\QueueInterface::class, fn () => $this->queue);
    $this->container->singleton(EventDispatcher::class, fn () => new \TondbadSwoole\Events\Dispatcher($this->container));
    $this->dispatcher = $this->container->make(EventDispatcher::class);
});

it('dispatches string events to closures', function () {
    $received = null;

    $this->dispatcher->listen('user.created', function (GenericEvent $event) use (&$received) {
        $received = $event->payload();
    });

    $this->dispatcher->dispatch('user.created', ['id' => 1]);

    expect($received)->toBe(['id' => 1]);
});

it('returns a DispatchResult', function () {
    $this->dispatcher->listen('test', fn () => 'ok');

    $result = $this->dispatcher->dispatch('test');

    expect($result)->toBeInstanceOf(DispatchResult::class);
    expect($result->responses)->toBe(['ok']);
    expect($result->stopped)->toBeFalse();
    expect($result->errors)->toBe([]);
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

it('passes typed events to typed listener parameters', function () {
    $event = new class() extends Event {
        public function __construct(public int $id = 2) {}
    };

    $listener = new class() {
        public ?object $event = null;

        public function handle(object $event): void
        {
            $this->event = $event;
        }
    };

    $this->container->bind(get_class($listener), fn () => $listener);
    $this->dispatcher->listen(get_class($event), get_class($listener));
    $this->dispatcher->dispatch($event);

    expect($listener->event)->toBe($event);
});

it('supports listener priority', function () {
    $order = [];

    $this->dispatcher->listen('order.placed', function () use (&$order) {
        $order[] = 'second';
    }, 0);
    $this->dispatcher->listen('order.placed', function () use (&$order) {
        $order[] = 'first';
    }, 10);

    $this->dispatcher->dispatch('order.placed');

    expect($order)->toBe(['first', 'second']);
});

it('supports stop propagation', function () {
    $called = [];

    $event = new class() extends Event {};
    $eventClass = $event::class;

    $this->dispatcher->listen($eventClass, function (Event $event) use (&$called) {
        $called[] = 'first';
        $event->stopPropagation();
    });
    $this->dispatcher->listen($eventClass, function () use (&$called) {
        $called[] = 'second';
    });

    $result = $this->dispatcher->dispatch($event);

    expect($called)->toBe(['first']);
    expect($result->stopped)->toBeTrue();
});

it('supports typed event class hierarchy', function () {
    abstract class ApplicationEvent extends Event {}
    class UserCreated extends ApplicationEvent {
        public function __construct(public string $email) {}
    }

    $parentCalled = false;
    $childCalled = false;

    $this->dispatcher->listen(ApplicationEvent::class, function (ApplicationEvent $e) use (&$parentCalled) {
        $parentCalled = true;
    });
    $this->dispatcher->listen(UserCreated::class, function (UserCreated $e) use (&$childCalled) {
        $childCalled = true;
    });

    $this->dispatcher->dispatch(new UserCreated('test@example.com'));

    expect($parentCalled)->toBeTrue();
    expect($childCalled)->toBeTrue();
});

it('dispatches DeadEvent for unhandled typed events', function () {
    $dead = null;

    $this->dispatcher->listen(DeadEvent::class, function (DeadEvent $e) use (&$dead) {
        $dead = $e->originalEvent;
    });

    $event = new class() extends Event {};
    $this->dispatcher->dispatch($event);

    expect($dead)->toBeInstanceOf($event::class);
});

it('supports wildcard string listeners', function () {
    $captured = [];

    $this->dispatcher->listen('user.*', function (GenericEvent $e) use (&$captured) {
        $captured[] = $e->name();
    });

    $this->dispatcher->dispatch('user.created', 1);
    $this->dispatcher->dispatch('user.deleted', 2);
    $this->dispatcher->dispatch('order.shipped', 3);

    expect($captured)->toBe(['user.created', 'user.deleted']);
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

it('forgets listeners by id', function () {
    $called = false;

    $id = $this->dispatcher->listen('cleanup', function () use (&$called) {
        $called = true;
    });

    $this->dispatcher->off($id);
    $this->dispatcher->dispatch('cleanup');

    expect($called)->toBeFalse();
});

it('forgets listeners by event', function () {
    $called = false;

    $this->dispatcher->listen('cleanup', function () use (&$called) {
        $called = true;
    });

    $this->dispatcher->forget('cleanup');
    $this->dispatcher->dispatch('cleanup');

    expect($called)->toBeFalse();
});

it('supports once listeners', function () {
    $called = 0;

    $this->dispatcher->once('single', function () use (&$called) {
        ++$called;
    });

    $this->dispatcher->dispatch('single');
    $this->dispatcher->dispatch('single');

    expect($called)->toBe(1);
});

it('isolates listener exceptions', function () {
    $this->dispatcher->listen('fail', function () {
        throw new \RuntimeException('boom');
    });
    $this->dispatcher->listen('fail', function () {
        return 'ok';
    });

    $result = $this->dispatcher->dispatch('fail');

    expect($result->responses)->toBe(['ok']);
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->exception)->toBeInstanceOf(\RuntimeException::class);
});

it('supports event subscribers', function () {
    $subscriber = new class() implements \TondbadSwoole\Events\Contracts\EventSubscriber {
        public static function getSubscribedEvents(): array
        {
            return [
                'sub.event' => 'onEvent',
            ];
        }

        public function onEvent(GenericEvent $event): string
        {
            return $event->payload();
        }
    };

    $this->container->bind(get_class($subscriber), fn () => $subscriber);
    $this->dispatcher->subscribe(get_class($subscriber));

    $result = $this->dispatcher->dispatch('sub.event', 'payload');

    expect($result->responses)->toBe(['payload']);
});

it('queues QueueableEvent listeners', function () {
    $event = new class() extends Event implements QueueableEvent {
        public function __construct(public string $value = 'x') {}

        public function toJobPayload(): array
        {
            return ['value' => $this->value];
        }

        public static function fromJobPayload(array $payload): static
        {
            return new self($payload['value']);
        }
    };

    $listener = new #[Listener(events: [], queued: true)] class() {
        public function handle($event): void
        {
            //
        }
    };

    $this->dispatcher->listen(get_class($event), get_class($listener));
    $this->dispatcher->dispatch(new $event('hello'));

    expect($this->queue->size())->toBe(1);
});
