<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\Contracts\QueueableEvent;
use TondbadSwoole\Queue\Jobs\Job;

class CallListenerJob extends Job
{
    private array|object $eventPayload;

    public function __construct(
        public readonly string $listenerClass,
        public readonly string $method,
        object $event,
        public readonly string $eventName,
    ) {
        if ($event instanceof QueueableEvent) {
            $this->eventPayload = ['queueable' => true, 'class' => $event::class, 'data' => $event->toJobPayload()];
        } else {
            $serialized = @serialize($event);

            if ($serialized === false) {
                throw new \RuntimeException('Queued event must implement QueueableEvent or be serializable.');
            }

            $this->eventPayload = $event;
        }
    }

    public function handle(?EventDispatcher $dispatcher = null): void
    {
        $dispatcher ??= app()?->container->make(EventDispatcher::class);

        if ($dispatcher === null) {
            throw new \RuntimeException('Cannot resolve event dispatcher for queued listener.');
        }

        $event = $this->restoreEvent();

        $dispatcher->callListener([$this->listenerClass, $this->method], $event, $this->eventName);
    }

    private function restoreEvent(): object
    {
        if (is_array($this->eventPayload) && ($this->eventPayload['queueable'] ?? false)) {
            $class = $this->eventPayload['class'];

            if (!is_subclass_of($class, QueueableEvent::class)) {
                throw new \RuntimeException("Queued event class [{$class}] does not implement QueueableEvent.");
            }

            return $class::fromJobPayload($this->eventPayload['data']);
        }

        if (is_object($this->eventPayload)) {
            return $this->eventPayload;
        }

        throw new \RuntimeException('Cannot restore queued event payload.');
    }
}
