<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use Throwable;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\Jobs\Job;

class Worker
{
    public function __construct(
        private readonly Container $container,
        private readonly ?FailedJobProviderInterface $failer = null,
    ) {
    }

    public function runNextJob(QueueInterface $connection, string $queue, int $maxTries = 1, int $sleep = 3, ?WorkerOptions $options = null): bool
    {
        $job = $connection->pop($queue);

        if ($job === null) {
            if ($sleep > 0) {
                sleep($sleep);
            }

            return false;
        }

        $this->process($job, $connection, $maxTries, $options);

        return true;
    }

    public function process(Job $job, ?QueueInterface $connection = null, ?int $maxTries = null, ?WorkerOptions $options = null): void
    {
        $maxTries ??= $options?->maxTries ?? 1;

        if ($connection === null) {
            $job->incrementAttempts();
        }

        $job->setConnection($connection);

        try {
            if ($this->shouldRateLimit($job, $options, $connection)) {
                return;
            }

            $this->container->call([$job, 'handle']);

            if ($connection !== null && $job->getJobId() !== null) {
                if ($job->getResult() !== null) {
                    $connection->setResult($job->getJobId(), $job->getResult());
                }

                $connection->markCompleted($job->getJobId());

                if ($job->shouldRemoveOnComplete()) {
                    $connection->delete($job->getJobId());
                }
            }
        } catch (Throwable $e) {
            $this->handleException($job, $connection, $e, $maxTries);
        } finally {
            $job->setConnection(null);
        }
    }

    protected function shouldRateLimit(Job $job, ?WorkerOptions $options, ?QueueInterface $connection): bool
    {
        if ($connection === null || $options === null || $options->rateLimiter === null) {
            return false;
        }

        $rateLimiter = $this->container->make(RateLimiter\RateLimiterInterface::class) ?? new RateLimiter\NullRateLimiter();

        if (!$rateLimiter instanceof RateLimiter\RateLimiterInterface) {
            return false;
        }

        $max = (int) ($options->rateLimiter['max'] ?? 0);
        $window = (int) ($options->rateLimiter['window'] ?? 60);
        $keyStrategy = $options->rateLimiter['key'] ?? 'queue';

        $key = match ($keyStrategy) {
            'class' => $job::class,
            'queue' => $job->getQueue() ?? 'default',
            default => (string) $keyStrategy,
        };

        if ($rateLimiter->tooManyAttempts($key, $max, $window)) {
            $delay = $rateLimiter->availableIn($key, $window);
            $connection->release($job->getJobId(), max(1, $delay));
            $connection->emit('rate_limited', ['job' => $job, 'queue' => $job->getQueue()]);

            return true;
        }

        $rateLimiter->hit($key, $window);

        return false;
    }

    protected function handleException(Job $job, ?QueueInterface $connection, Throwable $e, ?int $maxTries = null): void
    {
        $tries = $job->getMaxTries() ?? $maxTries;

        if ($job->hasFailed($tries)) {
            if ($job->shouldRemoveOnFail()) {
                $this->fail($job, $e);
            }

            if ($connection !== null && $job->getJobId() !== null) {
                $connection->markFailed($job->getJobId(), $e);

                if ($job->shouldRemoveOnFail()) {
                    $connection->delete($job->getJobId());
                }
            }

            if ($connection === null) {
                throw $e;
            }

            return;
        }

        if ($connection !== null) {
            $connection->release($job->getJobId(), $job->getBackoffDelay());

            return;
        }

        throw $e;
    }

    protected function fail(Job $job, Throwable $e): void
    {
        if ($this->failer !== null) {
            $this->failer->log($job, $e);
        }
    }
}
