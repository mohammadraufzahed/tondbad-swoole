<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Queue\QueueManager;

#[AsCommand('queue:status', 'Show queue metrics by status.')]
class QueueStatusCommand extends Command
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
        $metrics = $connection->getMetrics($this->queue);

        $rows = [];

        foreach ($metrics as $status => $count) {
            $rows[] = [$status, $count];
        }

        $output->table(['Status', 'Count'], $rows);

        return 0;
    }
}
