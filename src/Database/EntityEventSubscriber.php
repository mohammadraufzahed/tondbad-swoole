<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

interface EntityEventSubscriber
{
    /**
     * @return array<string, string>
     */
    public function getSubscribedEvents(): array;
}
