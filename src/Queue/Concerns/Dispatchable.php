<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Concerns;

use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\QueueManager;

trait Dispatchable
{
    public function dispatch(?string $queue = null, ?string $connection = null): static
    {
        $queueManager = app()->container->make(QueueInterface::class);

        if ($connection !== null) {
            $queueManager = app()->container->make(QueueManager::class)->connection($connection);
        }

        $queueManager->push($this, $queue);

        return $this;
    }

    public function onQueue(string $queue): static
    {
        return $this->dispatch($queue);
    }
}
