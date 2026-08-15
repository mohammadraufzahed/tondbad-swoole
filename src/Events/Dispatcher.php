<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher as EventDispatcherContract;
use TondbadSwoole\Events\Contracts\EventSubscriber;
use TondbadSwoole\Events\Contracts\QueueableEvent;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueInterface;

class Dispatcher implements EventDispatcherContract
{
    private int $counter = 0;

    /**
     * @var array<string, list<array{0:int,1:Closure,2:string,3:mixed}>>
     */
    private array $listeners = [];

    /**
     * @var array<string, list<array{0:int,1:Closure,2:string,3:mixed}>>
     */
    private array $wildcards = [];

    /**
     * @var array<string, list<Closure>>
     */
    private array $sorted = [];

    /**
     * @var array<string, true>
     */
    private array $sortedStale = [];

    /**
     * @var array<string, array{key: string, wildcard: bool}>
     */
    private array $idMap = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function listen(string|object $event, callable|array|string $listener, int $priority = 0): ListenerId
    {
        $key = $this->eventKey($event);
        $normalized = $this->normalizeListener($listener);
        $flags = $this->listenerFlags($normalized);
        $id = $this->nextId();
        $closure = ($flags['queued'] || $flags['async'])
            ? $this->wrapQueuedAsync($normalized, $flags['queued'], $flags['async'])
            : $this->wrapListener($normalized);

        if ($this->isWildcard($key)) {
            $this->wildcards[$key][] = [$priority, $closure, $id, $normalized];
        } else {
            $this->listeners[$key][] = [$priority, $closure, $id, $normalized];
        }

        $this->idMap[$id] = ['key' => $key, 'wildcard' => $this->isWildcard($key)];
        $this->markStale($key);

        return new ListenerId($id);
    }

    public function once(string|object $event, callable|array|string $listener, int $priority = 0): ListenerId
    {
        $id = $this->nextId();
        $key = $this->eventKey($event);
        $normalized = $this->normalizeListener($listener);
        $flags = $this->listenerFlags($normalized);

        $wrapper = function (object $event, ?string $eventName = null) use ($id, $normalized, $flags): mixed {
            $this->off(new ListenerId($id));

            if ($flags['queued']) {
                $this->pushToQueue($normalized, $event, $eventName);

                return null;
            }

            if ($flags['async']) {
                $this->runAsync($normalized, $event, $eventName);

                return null;
            }

            return $this->callListener($normalized, $event, $eventName);
        };

        if ($this->isWildcard($key)) {
            $this->wildcards[$key][] = [$priority, $wrapper, $id, $normalized];
        } else {
            $this->listeners[$key][] = [$priority, $wrapper, $id, $normalized];
        }

        $this->idMap[$id] = ['key' => $key, 'wildcard' => $this->isWildcard($key)];
        $this->markStale($key);

        return new ListenerId($id);
    }

    public function off(ListenerId|string $event, callable|array|string|null $listener = null): void
    {
        if ($event instanceof ListenerId) {
            $this->offById($event->value);

            return;
        }

        $key = $this->eventKey($event);

        if ($listener === null) {
            unset($this->listeners[$key], $this->wildcards[$key]);
            $this->markStale($key);

            return;
        }

        $normalized = $this->normalizeListener($listener);

        if ($this->isWildcard($key)) {
            $this->removeMatching($this->wildcards, $key, $normalized);
        } else {
            $this->removeMatching($this->listeners, $key, $normalized);
        }

        $this->markStale($key);
    }

    public function forget(string $event): void
    {
        $this->off($event);
    }

    public function subscribe(string|object $subscriber): void
    {
        $subscriber = is_string($subscriber) ? $this->container->make($subscriber) : $subscriber;

        if ($subscriber instanceof EventSubscriber) {
            foreach ($subscriber->getSubscribedEvents() as $event => $config) {
                [$method, $priority, $queued, $async] = $this->parseSubscriberConfig($config);
                $this->registerListener($event, [$subscriber, $method], $priority ?? 0, $queued, $async);
            }
        }

        if (method_exists($subscriber, 'subscribe')) {
            $reflection = new ReflectionMethod($subscriber, 'subscribe');
            $parameters = $reflection->getParameters();

            if (count($parameters) === 1 && $parameters[0]->getType() instanceof ReflectionNamedType && $parameters[0]->getType()->getName() === EventDispatcherContract::class) {
                $subscriber->subscribe($this);
            }
        }

        $reflection = new ReflectionClass($subscriber);

        $classAttributes = $reflection->getAttributes(Listener::class);
        foreach ($classAttributes as $attribute) {
            /** @var Listener $listener */
            $listener = $attribute->newInstance();
            foreach ($listener->events as $event) {
                $this->registerListener($event, [$subscriber, 'handle'], $listener->priority ?? 0, $listener->queued, $listener->async);
            }
        }

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Listener::class);
            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();
                foreach ($listener->events as $event) {
                    $this->registerListener($event, [$subscriber, $method->getName()], $listener->priority ?? 0, $listener->queued, $listener->async);
                }
            }
        }
    }

    public function dispatch(string|object $event, mixed $payload = null): DispatchResult
    {
        [$eventObject, $eventName] = $this->normalizeEvent($event, $payload);

        $listeners = $this->getListenersFor($eventObject, $eventName);

        if ($listeners === [] && $eventObject instanceof Event && !$eventObject instanceof DeadEvent) {
            return $this->dispatch(new DeadEvent($eventObject));
        }

        $responses = [];
        $errors = [];

        foreach ($listeners as $listener) {
            if ($eventObject instanceof Event && $eventObject->isPropagationStopped()) {
                break;
            }

            try {
                $responses[] = $listener($eventObject, $eventName);
            } catch (\Throwable $e) {
                $errors[] = new ListenerError(
                    $eventName,
                    $this->listenerName($listener),
                    $e,
                );
            }
        }

        return new DispatchResult(
            $eventObject,
            $responses,
            $eventObject instanceof Event ? $eventObject->isPropagationStopped() : false,
            $errors,
        );
    }

    public function until(string|object $event, mixed $payload = null): mixed
    {
        [$eventObject, $eventName] = $this->normalizeEvent($event, $payload);
        $listeners = $this->getListenersFor($eventObject, $eventName);

        foreach ($listeners as $listener) {
            if ($eventObject instanceof Event && $eventObject->isPropagationStopped()) {
                break;
            }

            try {
                $result = $listener($eventObject, $eventName);

                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                // until() does not collect errors; a failing listener is treated as no result.
            }
        }

        return null;
    }

    public function hasListeners(string|object $event): bool
    {
        return $this->getListenersForEventKey($this->eventKey($event)) !== [];
    }

    public function getListeners(string|object $event): array
    {
        return $this->getListenersForEventKey($this->eventKey($event));
    }

    /**
     * @param class-string|object $event
     */
    private function eventKey(string|object $event): string
    {
        return is_string($event) ? $event : $event::class;
    }

    private function isWildcard(string $key): bool
    {
        return str_contains($key, '*');
    }

    private function nextId(): string
    {
        return 'listener_' . ++$this->counter;
    }

    private function markStale(string $key): void
    {
        $this->sortedStale[$key] = true;

        foreach (array_keys($this->sorted) as $resolvedKey) {
            if ($resolvedKey === $key || $this->wildcardMatches($resolvedKey, $key) || $this->wildcardMatches($key, $resolvedKey)) {
                unset($this->sorted[$resolvedKey]);
            }
        }
    }

    private function wildcardMatches(string $pattern, string $subject): bool
    {
        if (!str_contains($pattern, '*')) {
            return false;
        }

        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $subject);
    }

    private function registerListener(string $event, callable|array $listener, int $priority, bool $queued, bool $async): void
    {
        if ($queued || $async) {
            $listener = $this->wrapQueuedAsync($listener, $queued, $async);
        }

        $this->listen($event, $listener, $priority);
    }

    /**
     * @return array{0: string, 1?: int, 2?: bool, 3?: bool}
     */
    private function parseSubscriberConfig(string|array $config): array
    {
        if (is_string($config)) {
            return [$config, 0, false, false];
        }

        return [
            $config[0] ?? '',
            $config[1] ?? 0,
            $config[2] ?? false,
            $config[3] ?? false,
        ];
    }

    private function offById(string $id): void
    {
        $meta = $this->idMap[$id] ?? null;

        if ($meta === null) {
            return;
        }

        if ($meta['wildcard']) {
            $this->removeById($this->wildcards, $meta['key'], $id);
        } else {
            $this->removeById($this->listeners, $meta['key'], $id);
        }

        unset($this->idMap[$id]);
        $this->markStale($meta['key']);
    }

    /**
     * @param array<string, list<array{0:int,1:Closure,2:string,3:mixed}>> $store
     */
    private function removeById(array &$store, string $key, string $id): void
    {
        if (!isset($store[$key])) {
            return;
        }

        $store[$key] = array_values(array_filter($store[$key], fn (array $entry) => $entry[2] !== $id));

        if ($store[$key] === []) {
            unset($store[$key]);
        }
    }

    /**
     * @param array<string, list<array{0:int,1:Closure,2:string,3:mixed}>> $store
     * @param array{0: class-string|object, 1: string}|callable $normalized
     */
    private function removeMatching(array &$store, string $key, array|callable $normalized): void
    {
        if (!isset($store[$key])) {
            return;
        }

        $store[$key] = array_values(array_filter($store[$key], function (array $entry) use ($normalized): bool {
            return !$this->listenersMatch($entry[3], $normalized);
        }));

        if ($store[$key] === []) {
            unset($store[$key]);
        }
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $a
     * @param array{0: class-string|object, 1: string}|callable $b
     */
    private function listenersMatch(array|callable $a, array|callable $b): bool
    {
        if ($a instanceof Closure && $b instanceof Closure) {
            return $a === $b;
        }

        if (is_array($a) && is_array($b)) {
            return $a[0] === $b[0] && ($a[1] ?? 'handle') === ($b[1] ?? 'handle');
        }

        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }

        return false;
    }

    /**
     * @return array{0: object, 1: string}
     */
    private function normalizeEvent(string|object $event, mixed $payload): array
    {
        if (is_string($event)) {
            if ($payload instanceof Event) {
                return [$payload, $event];
            }

            $subject = is_object($payload) ? $payload : null;

            return [new GenericEvent($event, $payload, $subject), $event];
        }

        return [$event, $event::class];
    }

    /**
     * @param class-string|object|callable $listener
     * @return array{0: class-string|object, 1: string}|callable
     */
    private function normalizeListener(callable|array|string $listener): array|callable
    {
        if (is_string($listener) && class_exists($listener)) {
            return [$listener, 'handle'];
        }

        if (is_array($listener) && is_string($listener[0]) && class_exists($listener[0])) {
            return [$listener[0], $listener[1] ?? 'handle'];
        }

        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('Listener is not callable.');
        }

        return $listener;
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    private function wrapListener(array|callable $listener): Closure
    {
        return function (object $event, ?string $eventName = null) use ($listener): mixed {
            return $this->callListener($listener, $event, $eventName);
        };
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     * @return array{queued: bool, async: bool}
     */
    private function listenerFlags(array|callable $listener): array
    {
        if (is_array($listener) && is_string($listener[0]) && class_exists($listener[0])) {
            $attributes = (new ReflectionClass($listener[0]))->getAttributes(Listener::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                return ['queued' => $instance->queued, 'async' => $instance->async];
            }
        }

        return ['queued' => false, 'async' => false];
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    private function wrapQueuedAsync(array|callable $listener, bool $queued, bool $async): Closure
    {
        return function (object $event, ?string $eventName = null) use ($listener, $queued, $async): mixed {
            if ($queued) {
                $this->pushToQueue($listener, $event, $eventName);

                return null;
            }

            if ($async) {
                $this->runAsync($listener, $event, $eventName);

                return null;
            }

            return $this->callListener($listener, $event, $eventName);
        };
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    private function pushToQueue(array|callable $listener, object $event, ?string $eventName): void
    {
        $queue = $this->resolveQueue();

        if ($queue === null) {
            return;
        }

        if (!is_array($listener) || !is_string($listener[0])) {
            throw new \RuntimeException('Queued listeners must be class-based.');
        }

        $job = new CallListenerJob($listener[0], $listener[1] ?? 'handle', $event, $eventName ?? $event->name());
        $queue->push($job);
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    private function runAsync(array|callable $listener, object $event, ?string $eventName): void
    {
        if (!class_exists(\OpenSwoole\Coroutine::class)) {
            return;
        }

        \OpenSwoole\Coroutine::create(function () use ($listener, $event, $eventName): void {
            try {
                $this->callListener($listener, $event, $eventName);
            } catch (\Throwable $e) {
                // Fire-and-forget async listeners should not crash the request.
            }
        });
    }

    private function resolveQueue(): ?QueueInterface
    {
        if (!$this->container->has(QueueInterface::class)) {
            return null;
        }

        try {
            return $this->container->make(QueueInterface::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return list<Closure>
     */
    private function getListenersFor(object $event, ?string $eventName): array
    {
        if ($eventName === null) {
            return $this->getListenersForEventKey($event::class);
        }

        $key = $event instanceof GenericEvent ? $eventName : $event::class;

        return $this->getListenersForEventKey($key);
    }

    /**
     * @return list<Closure>
     */
    private function getListenersForEventKey(string $key): array
    {
        if (!isset($this->sortedStale[$key]) && isset($this->sorted[$key])) {
            return $this->sorted[$key];
        }

        $entries = [];

        if (!$this->isWildcard($key)) {
            if (isset($this->listeners[$key])) {
                foreach ($this->listeners[$key] as $entry) {
                    $entries[$entry[2]] = $entry;
                }
            }

            // Parent class and interface listeners for typed events.
            if (class_exists($key) || interface_exists($key)) {
                foreach (array_merge(class_parents($key) ?: [], class_implements($key) ?: []) as $parent) {
                    if (isset($this->listeners[$parent])) {
                        foreach ($this->listeners[$parent] as $entry) {
                            $entries[$entry[2]] = $entry;
                        }
                    }
                }
            }
        }

        // Wildcards match the key (string events) or class name (typed events).
        foreach ($this->wildcards as $pattern => $patternEntries) {
            if ($this->wildcardMatches($pattern, $key)) {
                foreach ($patternEntries as $entry) {
                    $entries[$entry[2]] = $entry;
                }
            }
        }

        uasort($entries, function (array $a, array $b): int {
            if ($a[0] !== $b[0]) {
                return $b[0] <=> $a[0];
            }

            return strcmp($a[2], $b[2]);
        });

        $closures = array_values(array_map(fn (array $entry) => $entry[1], $entries));
        $this->sorted[$key] = $closures;
        unset($this->sortedStale[$key]);

        return $closures;
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    public function callListener(array|callable $listener, object $event, ?string $eventName = null): mixed
    {
        $resolved = $this->resolveInstance($listener);
        $reflection = $this->listenerReflection($resolved);
        $parameters = [];

        foreach ($reflection->getParameters() as $index => $parameter) {
            $resolvedParam = $this->resolveParameterFor($parameter, $index, $event, $eventName);

            if ($resolvedParam !== null) {
                $parameters[$parameter->getName()] = $resolvedParam;
            }
        }

        return $this->container->call($resolved, $parameters);
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     * @return array{0: object, 1: string}|callable
     */
    private function resolveInstance(array|callable $listener): array|callable
    {
        if (is_array($listener) && is_string($listener[0])) {
            $listener[0] = $this->container->make($listener[0]);
        }

        return $listener;
    }

    /**
     * @param array{0: object, 1: string}|callable $listener
     */
    private function listenerReflection(array|callable $listener): \ReflectionFunctionAbstract
    {
        if (is_array($listener)) {
            return new ReflectionMethod($listener[0], $listener[1]);
        }

        return new ReflectionFunction($listener);
    }

    private function resolveParameterFor(ReflectionParameter $parameter, int $index, object $event, ?string $eventName): mixed
    {
        $name = $parameter->getName();
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($event instanceof $typeName) {
                return $event;
            }

            if ($event instanceof GenericEvent) {
                $payload = $event->payload();

                if (is_object($payload) && $payload instanceof $typeName) {
                    return $payload;
                }
            }
        }

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && $event instanceof GenericEvent && is_array($event->payload())) {
            return $event->payload();
        }

        if ($name === 'eventName' && ($type === null || ($type instanceof ReflectionNamedType && $type->getName() === 'string'))) {
            return $eventName ?? $event->name();
        }

        if ($name === 'event') {
            if ($type === null) {
                return $event;
            }

            if ($type instanceof ReflectionNamedType && $type->getName() === 'string') {
                return $eventName ?? $event->name();
            }

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $parameterClass = $type->getName();

                if ($event instanceof $parameterClass) {
                    return $event;
                }
            }

            if ($type instanceof ReflectionNamedType && ($type->getName() === 'object' || $type->getName() === 'mixed')) {
                return $event;
            }
        }

        if ($type instanceof ReflectionNamedType && ($type->getName() === 'object' || $type->getName() === 'mixed')) {
            if ($index === 0) {
                return $event instanceof GenericEvent ? $event->payload() : $event;
            }

            return null;
        }

        if ($type === null && $index === 0) {
            if ($event instanceof GenericEvent) {
                return $event->payload();
            }

            return $event;
        }

        return null;
    }

    private function listenerName(Closure $listener): string
    {
        try {
            $reflection = new ReflectionFunction($listener);
            $name = $reflection->getName();
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();

            return $name . ($file !== false ? " ({$file}:{$line})" : '');
        } catch (\Throwable $e) {
            return 'closure';
        }
    }
}
