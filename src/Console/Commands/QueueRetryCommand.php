<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\QueueManager;

class QueueRetryCommand extends Command
{
    public function getName(): string
    {
        return 'queue:retry';
    }

    public function getDescription(): string
    {
        return 'Retry a failed job by failed_jobs id.';
    }

    public function run(array $args): int
    {
        $parsed = $this->parseArgs($args);
        $options = $parsed['options'];
        $positional = $parsed['positional'];
        $connectionName = $options['connection'] ?? null;
        $queue = $options['queue'] ?? 'default';
        $id = isset($positional[0]) ? (int) $positional[0] : null;

        if ($id === null || $id <= 0) {
            fwrite(STDERR, "Usage: queue:retry <failed-job-id> [--connection=...] [--queue=...]\n");

            return 1;
        }

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($connectionName);
        $failer = $app->container->make(FailedJobProviderInterface::class);

        $failed = $failer->find($id);

        if ($failed === null) {
            fwrite(STDERR, "Failed job {$id} not found.\n");

            return 1;
        }

        /** @var \TondbadSwoole\Queue\Jobs\Job $job */
        $job = unserialize($failed['payload']);
        $job->setAttempts(0);

        $originalId = $job->getJobId();

        if ($originalId !== null) {
            $connection->delete($originalId);
        }

        $connection->add($job, $queue);
        $failer->delete($id);

        fwrite(STDOUT, "Retried failed job {$id} on queue {$queue}.\n");

        return 0;
    }

    private function parseArgs(array $args): array
    {
        $options = [];
        $positional = [];

        foreach ($args as $arg) {
            $arg = (string) $arg;

            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                $options[$key] = $value === true ? true : $value;

                continue;
            }

            $positional[] = $arg;
        }

        return ['options' => $options, 'positional' => $positional];
    }
}
