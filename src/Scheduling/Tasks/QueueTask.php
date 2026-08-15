<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use InvalidArgumentException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class QueueTask implements Task
{
    private ?Job $job = null;

    public function __construct(
        private readonly string $jobClass,
        private readonly array $payload = [],
        private readonly ?string $queue = null,
        private readonly ?string $connection = null,
        ?string $serialized = null,
    ) {
        if ($serialized !== null) {
            $restored = unserialize(base64_decode($serialized));

            if (!$restored instanceof Job) {
                throw new InvalidArgumentException('Serialized queue task is not a Job instance.');
            }

            $this->job = $restored;
        } elseif (class_exists($this->jobClass)) {
            $this->job = new $this->jobClass(...$this->payload);
        }
    }

    public static function fromJob(Job $job, ?string $queue = null, ?string $connection = null): self
    {
        $instance = new self($job::class, [], $queue, $connection);
        $instance->job = $job;

        return $instance;
    }

    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed
    {
        $job = $this->job;

        if ($job === null) {
            $job = new $this->jobClass(...$this->payload);
        }

        if ($this->queue !== null) {
            $job->onQueue($this->queue);
        }

        $queue = $container->make(QueueManager::class)->connection($this->connection);

        return $queue->push($job, $this->queue);
    }

    public function toArray(): array
    {
        $serialized = $this->job !== null ? base64_encode(serialize($this->job)) : null;

        return [
            'type' => 'queue',
            'jobClass' => $this->jobClass,
            'payload' => $this->payload,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'serialized' => $serialized,
        ];
    }
}
