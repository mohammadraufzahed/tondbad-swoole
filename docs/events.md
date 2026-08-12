# Events & Listeners

The event dispatcher routes event payloads to registered listeners.

## Firing events

```php
event('user.created', $user);

// or with an object
event(new UserCreated($user));
```

## Listening to events

In a service provider:

```php
$dispatcher = app()->container->make(EventDispatcher::class);

$dispatcher->listen('user.created', function (User $user) {
    // send welcome email
});

$dispatcher->listen('user.created', [SendWelcomeEmail::class]);
```

A class-based listener receives the event as a typed method argument:

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // ...
    }
}
```

## Listener auto-discovery

Place listeners in `app/Listeners/` and mark them with `#[Listener]`:

```php
use TondbadSwoole\Events\Listener;

#[Listener(['user.created'], queued: true)]
class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // ...
    }
}
```

`EventServiceProvider` scans `app/Listeners` and auto-registers them at boot. Pass `queued: true` to dispatch the listener through the queue.

## Queued listeners

If a listener carries `#[Listener(..., queued: true)]` or implements `ShouldQueue`, it is dispatched to the queue instead of being executed synchronously.

## Halting propagation

```php
$result = event('user.created', $user);

// if a listener returns a non-null value, subsequent listeners may be skipped
```

Use `until()` to stop on the first non-null response.

## Forgetting listeners

```php
$dispatcher->forget('user.created');
```
