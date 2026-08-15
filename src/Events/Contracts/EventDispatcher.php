<?php

declare(strict_types=1);

namespace TondbadSwoole\Events\Contracts;

use TondbadSwoole\Events\DispatchResult;
use TondbadSwoole\Events\ListenerId;

interface EventDispatcher
{
    public function listen(string|object $event, callable|array|string $listener, int $priority = 0): ListenerId;

    public function once(string|object $event, callable|array|string $listener, int $priority = 0): ListenerId;

    public function off(ListenerId|string $event, callable|array|string|null $listener = null): void;

    public function forget(string $event): void;

    public function subscribe(string|object $subscriber): void;

    public function dispatch(string|object $event, mixed $payload = null): DispatchResult;

    public function until(string|object $event, mixed $payload = null): mixed;

    public function hasListeners(string|object $event): bool;

    /**
     * @return list<\Closure>
     */
    public function getListeners(string|object $event): array;

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    public function callListener(array|callable $listener, object $event, ?string $eventName = null): mixed;
}
