<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueManager;

#[AsCommand('queue:retry-failed', 'Retry all failed jobs for a queue.')]
class QueueRetryFailedCommand extends Command
{
    #[Option('connection', shortcut: 'c', mode: InputOption::VALUE_OPTIONAL, description: 'Queue connection name')]
    public ?string $connection = null;

    #[Option('queue', shortcut: 'Q', mode: InputOption::VALUE_OPTIONAL, description: 'Queue name', default: 'default')]
    public string $queue = 'default';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($this->connection);
        $failer = $app->container->make(FailedJobProviderInterface::class);

        $failed = $failer->forQueue($this->queue);
        $retried = 0;

        foreach ($failed as $row) {
            /** @var Job $job */
            $job = unserialize($row['payload']);
            $job->setAttempts(0);

            $originalId = $job->getJobId();

            if ($originalId !== null) {
                $connection->delete($originalId);
            }

            $connection->add($job, $this->queue);
            $failer->delete((int) $row['id']);
            $retried++;
        }

        $output->success("Retried {$retried} failed job(s) on queue {$this->queue}.");

        return 0;
    }
}
