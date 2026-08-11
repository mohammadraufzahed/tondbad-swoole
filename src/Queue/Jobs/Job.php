<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs;

use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\QueueManager;

abstract class Job
{
    protected ?int $jobId = null;

    protected int $attempts = 0;

    public ?int $tries = null;

    public ?int $timeout = null;

    public function dispatch(?string $queue = null, ?string $connection = null): self
    {
        $queueManager = app()->container->make(QueueInterface::class);

        if ($connection !== null) {
            $queueManager = app()->container->make(QueueManager::class)->connection($connection);
        }

        $queueManager->push($this, $queue);

        return $this;
    }

    public function onQueue(string $queue): self
    {
        return $this->dispatch($queue);
    }

    abstract public function handle(): void;

    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    public function setJobId(?int $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function incrementAttempts(): self
    {
        $this->attempts++;

        return $this;
    }

    public function getMaxTries(): ?int
    {
        return $this->tries;
    }

    public function getDelay(): int
    {
        return 0;
    }

    public function hasFailed(?int $maxTries = null): bool
    {
        if ($maxTries === null) {
            return false;
        }

        return $this->attempts >= $maxTries;
    }
}
