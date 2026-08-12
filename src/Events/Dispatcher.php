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
use TondbadSwoole\Queue\QueueInterface;

class Dispatcher
{
    /**
     * @var array<string, list<Closure>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly Container $container,
        private readonly ?QueueInterface $queue = null,
    ) {
    }

    /**
     * @param callable|array{0: class-string|object, 1: string}|class-string $listener
     */
    public function listen(string $event, callable|array|string $listener): void
    {
        $listener = $this->normalizeListener($listener);

        if ($this->shouldQueue($listener)) {
            $this->listeners[$event][] = $this->queueListenerClosure($listener);

            return;
        }

        $this->listeners[$event][] = $this->listenerClosure($listener);
    }

    public function dispatch(string|object $event, mixed $payload = null): array
    {
        [$eventName, $payload] = $this->normalizeEvent($event, $payload);
        $responses = [];

        foreach ($this->getListeners($eventName) as $listener) {
            $responses[] = $listener($payload, $eventName);
        }

        return $responses;
    }

    public function until(string|object $event, mixed $payload = null): mixed
    {
        [$eventName, $payload] = $this->normalizeEvent($event, $payload);

        foreach ($this->getListeners($eventName) as $listener) {
            $response = $listener($payload, $eventName);

            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * @param class-string|object $subscriber
     */
    public function subscribe(string|object $subscriber): void
    {
        $subscriber = is_string($subscriber) ? $this->container->make($subscriber) : $subscriber;

        if (!is_object($subscriber) || !method_exists($subscriber, 'subscribe')) {
            throw new \InvalidArgumentException('Subscriber must have a subscribe method.');
        }

        $subscriber->subscribe($this);
    }

    /**
     * @return list<Closure>
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    public function callListener(array|callable $listener, mixed $payload, ?string $eventName = null): mixed
    {
        $listener = $this->normalizeListener($listener);

        if (is_array($listener) && is_string($listener[0])) {
            $listener[0] = $this->container->make($listener[0]);
        }

        $parameters = $this->resolveParameters($listener, $payload, $eventName);

        return $this->container->call($listener, $parameters);
    }

    /**
     * @param callable|array{0: class-string|object, 1: string}|class-string $listener
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
    private function shouldQueue(array|callable $listener): bool
    {
        if (!is_array($listener) || !is_string($listener[0])) {
            return false;
        }

        $listenerClass = $listener[0];

        if (is_subclass_of($listenerClass, ShouldQueue::class) || in_array(ShouldQueue::class, class_implements($listenerClass), true)) {
            return true;
        }

        $attribute = (new ReflectionClass($listenerClass))->getAttributes(Listener::class)[0] ?? null;

        if ($attribute !== null) {
            return $attribute->newInstance()->queued;
        }

        return false;
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable $listener
     */
    private function listenerClosure(array|callable $listener): Closure
    {
        return function (mixed $payload, ?string $eventName = null) use ($listener): mixed {
            return $this->callListener($listener, $payload, $eventName);
        };
    }

    /**
     * @param array{0: class-string, 1: string} $listener
     */
    private function queueListenerClosure(array $listener): Closure
    {
        return function (mixed $payload, ?string $eventName = null) use ($listener): void {
            if ($this->queue === null) {
                return;
            }

            $this->queue->push(new CallListenerJob($listener[0], $listener[1], $eventName ?? '', $payload));
        };
    }

    /**
     * @return array{0: string, 1: mixed}
     */
    private function normalizeEvent(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            $payload = $event;
            $event = $event::class;
        }

        return [$event, $payload];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveParameters(array|callable $listener, mixed $payload, ?string $eventName): array
    {
        $reflection = $this->getReflection($listener);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters = array_merge($parameters, $this->resolveParameter($parameter, $payload, $eventName));
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveParameter(ReflectionParameter $parameter, mixed $payload, ?string $eventName): array
    {
        $name = $parameter->getName();
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if (is_object($payload) && $payload instanceof $typeName) {
                return [$name => $payload];
            }

            if ($eventName !== null && class_exists($eventName) && $eventName === $typeName) {
                return [$name => $payload];
            }

            if ($typeName === 'array' && $payload !== null && !is_object($payload)) {
                return [$name => is_array($payload) ? $payload : [$payload]];
            }
        }

        if ($name === 'event' || $name === 'eventName') {
            return [$name => $eventName ?? $payload];
        }

        return [$name => $payload];
    }

    private function getReflection(array|callable $listener): \ReflectionFunctionAbstract
    {
        if (is_array($listener) && is_object($listener[0])) {
            return new ReflectionMethod($listener[0], $listener[1]);
        }

        if (is_array($listener) && is_string($listener[0])) {
            return new ReflectionMethod($listener[0], $listener[1]);
        }

        if ($listener instanceof Closure || is_string($listener)) {
            return new ReflectionFunction($listener);
        }

        throw new \InvalidArgumentException('Unsupported listener type.');
    }
}
