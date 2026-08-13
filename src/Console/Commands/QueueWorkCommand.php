<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Queue\Drivers\RedisQueue;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\Worker;
use TondbadSwoole\Queue\WorkerOptions;

class QueueWorkCommand extends Command
{
    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Start processing jobs on a queue connection.';
    }

    public function run(array $args): int
    {
        $options = $this->parseOptions($args);
        $connectionName = $options['connection'] ?? null;
        $queue = $options['queue'] ?? 'default';
        $sleep = isset($options['sleep']) ? (int) $options['sleep'] : 3;
        $tries = isset($options['tries']) ? (int) $options['tries'] : 1;
        $maxJobs = isset($options['max-jobs']) ? (int) $options['max-jobs'] : 0;
        $stopWhenEmpty = isset($options['stop-when-empty']);
        $concurrency = isset($options['concurrency']) ? (int) $options['concurrency'] : 1;
        $rateLimiter = $this->parseRateLimiter($options['rate-limit'] ?? null);

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $worker = $app->container->make(Worker::class);
        $connection = $queueManager->connection($connectionName);

        $workerOptions = new WorkerOptions(
            concurrency: max(1, $concurrency),
            maxTries: $tries,
            sleep: $connection instanceof RedisQueue ? 0 : $sleep,
            maxJobs: $maxJobs > 0 ? $maxJobs : null,
            stopWhenEmpty: $stopWhenEmpty,
            rateLimiter: $rateLimiter,
        );

        $canRunConcurrent = $workerOptions->concurrency > 1
            && class_exists(\OpenSwoole\Coroutine::class)
            && method_exists(\OpenSwoole\Coroutine::class, 'run')
            && method_exists(\OpenSwoole\Coroutine::class, 'create');

        if ($canRunConcurrent) {
            $this->enableSwooleHooks();

            \OpenSwoole\Coroutine::run(function () use ($connection, $queue, $worker, $workerOptions): void {
                $this->runConcurrent($connection, $queue, $worker, $workerOptions);
            });

            return 0;
        }

        $this->runSequential($connection, $queue, $worker, $workerOptions);

        return 0;
    }

    private function runSequential(\TondbadSwoole\Queue\QueueInterface $connection, string $queue, Worker $worker, WorkerOptions $options): void
    {
        $jobsProcessed = 0;

        while (true) {
            $ran = $worker->runNextJob($connection, $queue, $options->maxTries, $options->sleep, $options);

            if ($ran) {
                $jobsProcessed++;
            }

            if ($options->maxJobs !== null && $jobsProcessed >= $options->maxJobs) {
                break;
            }

            if ($options->stopWhenEmpty && !$ran) {
                break;
            }
        }
    }

    private function runConcurrent(\TondbadSwoole\Queue\QueueInterface $connection, string $queue, Worker $worker, WorkerOptions $options): void
    {
        $channel = new \OpenSwoole\Coroutine\Channel($options->concurrency);

        for ($i = 0; $i < $options->concurrency; $i++) {
            \OpenSwoole\Coroutine::create(function () use ($channel, $connection, $worker, $options): void {
                while (true) {
                    $job = $channel->pop();

                    if ($job === false) {
                        break;
                    }

                    $worker->process($job, $connection, $options->maxTries, $options);
                }
            });
        }

        $jobsProcessed = 0;

        while (true) {
            $job = $connection->pop($queue);

            if ($job === null) {
                if ($options->stopWhenEmpty) {
                    break;
                }

                usleep($options->sleep * 1000000);
                continue;
            }

            $channel->push($job);
            $jobsProcessed++;

            if ($options->maxJobs !== null && $jobsProcessed >= $options->maxJobs) {
                break;
            }
        }

        $channel->close();
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                $options[$key] = $value === true ? true : $value;
            }
        }

        return $options;
    }

    private function parseRateLimiter(mixed $value): ?array
    {
        if ($value === null || $value === true) {
            return null;
        }

        if (is_string($value) && str_contains($value, ':')) {
            [$max, $window] = explode(':', $value, 2);

            return [
                'max' => (int) $max,
                'window' => (int) $window,
                'key' => 'queue',
            ];
        }

        if (is_string($value) || is_int($value)) {
            return [
                'max' => (int) $value,
                'window' => 60,
                'key' => 'queue',
            ];
        }

        return null;
    }

    private function enableSwooleHooks(): void
    {
        if (!class_exists(\OpenSwoole\Runtime::class)) {
            return;
        }

        $flags = (int) \OpenSwoole\Runtime::getHookFlags();

        if (($flags & \OpenSwoole\Runtime::HOOK_ALL) === 0) {
            \OpenSwoole\Runtime::enableCoroutine(\OpenSwoole\Runtime::HOOK_ALL);
        }
    }
}
