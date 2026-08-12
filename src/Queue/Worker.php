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

    public function runNextJob(QueueInterface $connection, string $queue, int $maxTries = 1, int $sleep = 3): bool
    {
        $job = $connection->pop($queue);

        if ($job === null) {
            if ($sleep > 0) {
                sleep($sleep);
            }

            return false;
        }

        $this->process($job, $connection, $maxTries);

        return true;
    }

    public function process(Job $job, ?QueueInterface $connection = null, ?int $maxTries = null): void
    {
        $maxTries ??= 1;

        if ($connection === null) {
            $job->incrementAttempts();
        }

        $job->setConnection($connection);

        try {
            $this->container->call([$job, 'handle']);

            if ($connection !== null && $job->getJobId() !== null) {
                if ($job->shouldRemoveOnComplete()) {
                    $connection->delete($job->getJobId());
                } else {
                    $connection->markCompleted($job->getJobId());
                }

                $connection->emit('completed', ['job' => $job, 'result' => null]);
            }
        } catch (Throwable $e) {
            $this->handleException($job, $connection, $e, $maxTries);
        } finally {
            $job->setConnection(null);
        }
    }

    protected function handleException(Job $job, ?QueueInterface $connection, Throwable $e, ?int $maxTries = null): void
    {
        $tries = $job->getMaxTries() ?? $maxTries;

        if ($job->hasFailed($tries)) {
            $connection?->emit('failed', ['job' => $job, 'exception' => $e]);

            if ($job->shouldRemoveOnFail()) {
                $this->fail($job, $e);
            }

            if ($connection !== null && $job->getJobId() !== null) {
                if ($job->shouldRemoveOnFail()) {
                    $connection->delete($job->getJobId());
                } else {
                    $connection->markFailed($job->getJobId());
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
