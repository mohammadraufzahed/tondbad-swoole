# Events & Listeners

The event dispatcher is a single, OpenSwoole-safe bus that routes both string-based and typed events to listeners. It is inspired by Symfony EventDispatcher (priority, propagation, subscribers), Guava EventBus (typed event hierarchy, dead events), and Node.js EventEmitter (`once`, `off`, wildcards).

## Firing events

Use the `event()` helper or the `EventDispatcher` contract directly:

```php
use TondbadSwoole\Events\Event;

final class UserCreated extends Event
{
    public function __construct(public readonly int $id) {}
}

$result = event(new UserCreated(1));
$result = event('user.created', ['id' => 1]);
```

String events are wrapped in a `GenericEvent` object and support wildcards such as `user.*`.

The `event()` helper returns a `DispatchResult`:

```php
$result = event('order.placed');
$result->responses; // list< mixed >
$result->stopped; // bool
$result->errors;  // list< ListenerError >
```

## The `Event` contract

All typed events may extend `Event` to control propagation:

```php
use TondbadSwoole\Events\Event;

final class OrderPlaced extends Event
{
    public function __construct(public readonly int $orderId) {}
}

$dispatcher->listen(OrderPlaced::class, function (OrderPlaced $event) {
    if ($event->orderId === 0) {
        $event->stopPropagation();
    }
});
```

When propagation is stopped, later listeners are skipped. `until()` stops on the first non-null response.

## Listening

### Direct registration

```php
use TondbadSwoole\Events\Contracts\EventDispatcher;

$dispatcher = app()->container->make(EventDispatcher::class);

$dispatcher->listen('user.created', function (UserCreated $event) {
    // ...
}, priority: 10);

$id = $dispatcher->once('user.created', function ($event) {
    // runs once, then is removed
});

$dispatcher->off($id);
$dispatcher->forget('user.created');
```

### Class-based listeners

```php
namespace App\Listeners;

use App\Events\UserCreated;

class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // ...
    }
}
```

```php
$dispatcher->listen(UserCreated::class, [SendWelcomeEmail::class]);
```

### `#[Listener]` attribute

```php
use TondbadSwoole\Events\Listener;

#[Listener(events: [UserCreated::class], priority: 10, queued: true)]
class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // ...
    }
}
```

`priority` changes the execution order. `queued: true` pushes the listener onto the queue. `async: true` runs it in a coroutine. When placed on a method, only that method is registered:

```php
class UserSubscriber
{
    #[Listener(events: [UserCreated::class])]
    public function onUserCreated(UserCreated $event): void {}
}
```

### `EventSubscriber` interface

```php
use TondbadSwoole\Events\Contracts\EventSubscriber;

class UserSubscriber implements EventSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserCreated::class => 'onUserCreated',
            'user.deleted' => 'onUserDeleted',
        ];
    }
}
```

```php
$dispatcher->subscribe(UserSubscriber::class);
```

### Wildcards

```php
$dispatcher->listen('user.*', function (GenericEvent $event) {
    // matches user.created, user.deleted, ...
});
```

## Priority and `until`

Listeners are sorted by priority. Call `until()` to stop on the first non-null return value:

```php
$dispatcher->listen('route.match', fn () => null);
$dispatcher->listen('route.match', fn () => 'found');

$result = $dispatcher->until('route.match'); // 'found'
```

## Dead events

If no listener matches a typed event, a `DeadEvent` is dispatched. Listen for it to audit dropped events:

```php
use TondbadSwoole\Events\DeadEvent;

$dispatcher->listen(DeadEvent::class, function (DeadEvent $event) {
    logger()->warning('Unhandled event', ['event' => $event->originalEvent::class]);
});
```

## Queued and async listeners

A queued listener receives the event through the queue:

```php
#[Listener(events: [UserCreated::class], queued: true)]
class SendWelcomeEmail {}
```

The event must implement `QueueableEvent` or be serializable. A `CallListenerJob` is queued and calls the listener later.

An async listener runs in a coroutine:

```php
#[Listener(events: [UserCreated::class], async: true)]
class CountUsers {}
```

## Framework lifecycle events

The dispatcher is wired into framework modules. Each module dispatches typed events.

### Queue

`TondbadSwoole\Queue\Events\QueueEvent`:

```
queue.job.added
queue.job.completed
queue.job.failed
queue.flow.added
queue.paused
queue.drained
...
```

### ORM

`TondbadSwoole\Database\Events\OrmEvent`:

```
orm.postPersist
orm.postUpdate
orm.postRemove
orm.postLoad
orm.onFlush
```

### Auth

`TondbadSwoole\Auth\Events\AuthEvent`:

```
auth.login
auth.logout
auth.register
auth.failed
auth.revoked
auth.token.issued
auth.session.refreshed
auth.identity.linked
```

### Cache

`TondbadSwoole\Core\Cache\Events\CacheEvent`:

```
cache.hit
cache.miss
cache.set
cache.delete
cache.clear
cache.invalidated
cache.refresh
```

### Routing

`TondbadSwoole\Core\Route\Events\RouteEvent`:

```
route.dispatching
route.matched
route.not_found
route.method_not_allowed
route.fallback
route.dispatched
```

### Console

`TondbadSwoole\Console\Events\ConsoleEvent`:

```
console.starting
console.terminated
console.failed
console.not_found
```

### gRPC

`TondbadSwoole\GRPC\Events\GrpcEvent`:

```
grpc.request
grpc.response
```

### Scheduler

`TondbadSwoole\Scheduling\Events\ScheduleEvent`:

```
schedule.starting
schedule.ran
schedule.failed
```

## Helpers

```php
$dispatcher = dispatcher();
$result = event('order.placed', $payload);
```

## OpenSwoole safety

- The dispatcher is a per-worker singleton.
- Listener lists are snapshotted before dispatch so registrations during dispatch do not affect the current run.
- Exceptions in one listener are isolated; the rest of the listeners run and errors are collected in `DispatchResult`.
- Queued listeners never serialize closures. Use `QueueableEvent` or serializable event objects.
- The dispatcher lazily resolves the queue from the container to avoid circular dependencies.
