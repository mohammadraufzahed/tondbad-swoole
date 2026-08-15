<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use InvalidArgumentException;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Queue\Drivers\RedisQueue;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\Worker;
use TondbadSwoole\Queue\WorkerOptions;
use TondbadSwoole\Validation\Schema;

#[AsCommand('queue:work', 'Start processing jobs on a queue connection.', coroutine: false)]
class QueueWorkCommand extends Command
{
    #[Option('connection', shortcut: 'c', mode: InputOption::VALUE_OPTIONAL, description: 'Queue connection name')]
    public ?string $connection = null;

    #[Option('queue', shortcut: 'Q', mode: InputOption::VALUE_OPTIONAL, description: 'Queue name', default: 'default')]
    public string $queue = 'default';

    #[Option('sleep', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Seconds to sleep when empty', default: 3)]
    public int $sleep = 3;

    #[Option('tries', shortcut: 't', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Number of tries', default: 1)]
    public int $tries = 1;

    #[Option('max-jobs', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Maximum jobs to process', default: 0)]
    public int $maxJobs = 0;

    #[Option('stop-when-empty', mode: InputOption::VALUE_NONE, description: 'Stop when queue is empty')]
    public bool $stopWhenEmpty = false;

    #[Option('concurrency', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Concurrent workers', default: 1)]
    public int $concurrency = 1;

    #[Option('rate-limit', mode: InputOption::VALUE_OPTIONAL, description: 'Rate limit as max or max:window')]
    public ?string $rateLimit = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rateLimiter = $this->parseRateLimiter($this->rateLimit);
        } catch (InvalidArgumentException $e) {
            $output->error($e->getMessage());

            return 1;
        }

        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $worker = $app->container->make(Worker::class);
        $connection = $queueManager->connection($this->connection);

        $workerOptions = new WorkerOptions(
            concurrency: max(1, $this->concurrency),
            maxTries: $this->tries,
            sleep: $connection instanceof RedisQueue ? 0 : $this->sleep,
            maxJobs: $this->maxJobs > 0 ? $this->maxJobs : null,
            stopWhenEmpty: $this->stopWhenEmpty,
            rateLimiter: $rateLimiter,
        );

        $canRunConcurrent = $workerOptions->concurrency > 1
            && class_exists(\OpenSwoole\Coroutine::class)
            && class_exists(\OpenSwoole\Atomic::class)
            && method_exists(\OpenSwoole\Coroutine::class, 'run')
            && method_exists(\OpenSwoole\Coroutine::class, 'create');

        if ($canRunConcurrent) {
            $this->enableSwooleHooks();

            \OpenSwoole\Coroutine::run(function () use ($queueManager, $worker, $workerOptions): void {
                $this->runConcurrent($queueManager, $this->connection, $this->queue, $worker, $workerOptions);
            });

            return 0;
        }

        $this->runSequential($connection, $this->queue, $worker, $workerOptions);

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

    private function runConcurrent(QueueManager $queueManager, ?string $connectionName, string $queue, Worker $worker, WorkerOptions $options): void
    {
        $jobsProcessed = new \OpenSwoole\Atomic(0);
        $done = new \OpenSwoole\Coroutine\Channel($options->concurrency);

        for ($i = 0; $i < $options->concurrency; $i++) {
            \OpenSwoole\Coroutine::create(function () use ($queueManager, $connectionName, $queue, $worker, $options, $jobsProcessed, $done): void {
                $connection = $queueManager->connection($connectionName, true);

                while (true) {
                    if ($options->maxJobs !== null && $jobsProcessed->get() >= $options->maxJobs) {
                        break;
                    }

                    $job = $connection->pop($queue);

                    if ($job === null) {
                        if ($options->stopWhenEmpty) {
                            break;
                        }

                        if ($options->sleep > 0) {
                            usleep($options->sleep * 1000000);
                        }

                        continue;
                    }

                    $worker->process($job, $connection, $options->maxTries, $options);
                    $jobsProcessed->add(1);
                }

                $done->push(true);
            });
        }

        for ($i = 0; $i < $options->concurrency; $i++) {
            $done->pop();
        }
    }

    private function parseRateLimiter(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $schema = Schema::object([
            'max' => Schema::int()->coerce()->required(),
            'window' => Schema::int()->coerce()->default(60),
            'key' => Schema::string()->default('queue'),
        ])->lax();

        if (str_contains($value, ':')) {
            [$max, $window] = explode(':', $value, 2);
            $result = $schema->safeParse(['max' => $max, 'window' => $window]);

            if (!$result->valid) {
                throw new InvalidArgumentException('Invalid --rate-limit value: ' . implode('; ', array_column($result->errors, 'message')));
            }

            return $result->data;
        }

        $result = $schema->safeParse(['max' => $value]);

        if (!$result->valid) {
            throw new InvalidArgumentException('Invalid --rate-limit value: ' . implode('; ', array_column($result->errors, 'message')));
        }

        return $result->data;
    }

    private function enableSwooleHooks(): void
    {
        if (!class_exists(\OpenSwoole\Runtime::class) || !defined('SWOOLE_HOOK_ALL')) {
            return;
        }

        $flags = (int) \OpenSwoole\Runtime::getHookFlags();

        if (($flags & SWOOLE_HOOK_ALL) === 0) {
            \OpenSwoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
        }
    }
}
