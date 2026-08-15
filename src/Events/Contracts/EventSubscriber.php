<?php

declare(strict_types=1);

namespace TondbadSwoole\Events\Contracts;

interface EventSubscriber
{
    /**
     * @return array<string, string|array{0: string, 1?: int}|array{0: string, 1?: int, 2?: array<string, mixed>}>
     */
    public static function getSubscribedEvents(): array;
}
