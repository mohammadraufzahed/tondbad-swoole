<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

use TondbadSwoole\Queue\Jobs\Job;

class CallListenerJob extends Job
{
    public function __construct(
        public readonly string $listenerClass,
        public readonly string $method,
        public readonly mixed $event,
        public readonly mixed $payload,
    ) {
    }

    public function handle(?Dispatcher $dispatcher = null): void
    {
        $dispatcher ??= app()?->container->make(Dispatcher::class);

        if ($dispatcher === null) {
            throw new \RuntimeException('Cannot resolve event dispatcher for queued listener.');
        }

        $dispatcher->callListener([$this->listenerClass, $this->method], $this->payload, is_string($this->event) ? $this->event : null);
    }
}
