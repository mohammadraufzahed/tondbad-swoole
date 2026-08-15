<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\QueueManager;

#[AsCommand('queue:retry', 'Retry a failed job by failed_jobs id.')]
class QueueRetryCommand extends Command
{
    #[Argument('id', mode: InputArgument::REQUIRED, schema: 'int', description: 'Failed job id')]
    public int $id;

    #[Option('connection', shortcut: 'c', mode: InputOption::VALUE_OPTIONAL, description: 'Queue connection name')]
    public ?string $connection = null;

    #[Option('queue', shortcut: 'Q', mode: InputOption::VALUE_OPTIONAL, description: 'Queue name', default: 'default')]
    public string $queue = 'default';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->id <= 0) {
            $output->error('Usage: queue:retry <failed-job-id> [--connection=...] [--queue=...]');

            return 1;
        }

        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($this->connection);
        $failer = $app->container->make(FailedJobProviderInterface::class);

        $failed = $failer->find($this->id);

        if ($failed === null) {
            $output->error("Failed job {$this->id} not found.");

            return 1;
        }

        /** @var \TondbadSwoole\Queue\Jobs\Job $job */
        $job = unserialize($failed['payload']);
        $job->setAttempts(0);

        $originalId = $job->getJobId();

        if ($originalId !== null) {
            $connection->delete($originalId);
        }

        $connection->add($job, $this->queue);
        $failer->delete($this->id);

        $output->success("Retried failed job {$this->id} on queue {$this->queue}.");

        return 0;
    }
}
