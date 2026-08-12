<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueManager;

class QueueRetryFailedCommand extends Command
{
    public function getName(): string
    {
        return 'queue:retry-failed';
    }

    public function getDescription(): string
    {
        return 'Retry all failed jobs for a queue.';
    }

    public function run(array $args): int
    {
        $options = $this->parseOptions($args);
        $connectionName = $options['connection'] ?? null;
        $queue = $options['queue'] ?? 'default';

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($connectionName);
        $failer = $app->container->make(FailedJobProviderInterface::class);

        $failed = $failer->forQueue($queue);
        $retried = 0;

        foreach ($failed as $row) {
            /** @var Job $job */
            $job = unserialize($row['payload']);
            $job->setAttempts(0);

            $originalId = $job->getJobId();

            if ($originalId !== null) {
                $connection->delete($originalId);
            }

            $connection->add($job, $queue);
            $failer->delete((int) $row['id']);
            $retried++;
        }

        fwrite(STDOUT, "Retried {$retried} failed job(s) on queue {$queue}.\n");

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
