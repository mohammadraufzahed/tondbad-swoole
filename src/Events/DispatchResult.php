<?php

declare(strict_types=1);

namespace TondbadSwoole\Events;

final readonly class DispatchResult
{
    /**
     * @param list<mixed> $responses
     * @param list<ListenerError> $errors
     */
    public function __construct(
        public object $event,
        public array $responses,
        public bool $stopped,
        public array $errors,
    ) {
    }
}
