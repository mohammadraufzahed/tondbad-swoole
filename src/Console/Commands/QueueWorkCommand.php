<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\Worker;

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

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $worker = $app->container->make(Worker::class);
        $connection = $queueManager->connection($connectionName);

        $jobsProcessed = 0;

        while (true) {
            $ran = $worker->runNextJob($connection, $queue, $tries, $sleep);

            if ($ran) {
                $jobsProcessed++;
            }

            if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                break;
            }

            if ($stopWhenEmpty && !$ran) {
                break;
            }
        }

        return 0;
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
}
