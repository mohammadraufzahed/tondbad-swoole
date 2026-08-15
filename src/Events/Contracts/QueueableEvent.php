<?php

declare(strict_types=1);

namespace TondbadSwoole\Events\Contracts;

interface QueueableEvent
{
    public function toJobPayload(): array;

    public static function fromJobPayload(array $payload): static;
}
