<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs;

use TondbadSwoole\Queue\Concerns\Dispatchable;
use TondbadSwoole\Queue\Jobs\Backoff\BackoffFactory;
use TondbadSwoole\Queue\Jobs\Backoff\BackoffStrategy;
use TondbadSwoole\Queue\QueueInterface;

abstract class Job
{
    use Dispatchable;

    protected ?int $jobId = null;

    protected int $attempts = 0;

    public ?int $tries = null;

    public ?int $timeout = null;

    protected ?string $queue = null;

    protected ?QueueInterface $connection = null;

    protected ?int $delay = null;

    protected ?int $priority = null;

    protected ?string $customJobId = null;

    protected ?BackoffStrategy $backoff = null;

    protected bool $removeOnComplete = true;

    protected bool $removeOnFail = true;

    protected int $progress = 0;

    /**
     * @var array<string, string>
     */
    private static array $optionMap = [
        'queue' => 'onQueue',
        'delay' => 'delay',
        'priority' => 'priority',
        'tries' => 'tries',
        'timeout' => 'timeout',
        'backoff' => 'backoff',
        'jobId' => 'jobId',
        'removeOnComplete' => 'removeOnComplete',
        'removeOnFail' => 'removeOnFail',
        'progress' => 'progress',
    ];

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

    public function tries(int $tries): self
    {
        $this->tries = $tries;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function delay(int $seconds): self
    {
        $this->delay = $seconds;

        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay ?? 0;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function jobId(string $id): self
    {
        $this->customJobId = $id;

        return $this;
    }

    public function getCustomJobId(): ?string
    {
        return $this->customJobId;
    }

    public function backoff(int|array|BackoffStrategy $backoff): self
    {
        $this->backoff = BackoffFactory::make($backoff);

        return $this;
    }

    public function getBackoff(): ?BackoffStrategy
    {
        return $this->backoff;
    }

    public function getBackoffDelay(): int
    {
        if ($this->backoff === null) {
            return 0;
        }

        return $this->backoff->delay($this->attempts);
    }

    public function removeOnComplete(bool $remove = true): self
    {
        $this->removeOnComplete = $remove;

        return $this;
    }

    public function shouldRemoveOnComplete(): bool
    {
        return $this->removeOnComplete;
    }

    public function removeOnFail(bool $remove = true): self
    {
        $this->removeOnFail = $remove;

        return $this;
    }

    public function shouldRemoveOnFail(): bool
    {
        return $this->removeOnFail;
    }

    public function setConnection(?QueueInterface $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection(): ?QueueInterface
    {
        return $this->connection;
    }

    public function __serialize(): array
    {
        $data = get_object_vars($this);

        unset($data['connection']);

        return $data;
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function progress(int $progress): self
    {
        $this->progress = max(0, min(100, $progress));

        if ($this->connection !== null && $this->jobId !== null) {
            $this->connection->progress($this->jobId, $this->progress);
            $this->connection->emit('progress', ['job' => $this, 'progress' => $this->progress]);
        }

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function hasFailed(?int $maxTries = null): bool
    {
        if ($maxTries === null) {
            return false;
        }

        return $this->attempts >= $maxTries;
    }

    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            if ($value === null) {
                continue;
            }

            $method = self::$optionMap[$key] ?? null;

            if ($method === null) {
                continue;
            }

            $this->{$method}($value);
        }

        return $this;
    }
}
