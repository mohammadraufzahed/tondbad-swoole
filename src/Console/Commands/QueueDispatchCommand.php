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
use TondbadSwoole\Queue\QueueManager;

#[AsCommand('queue:dispatch', 'Dispatch a job onto a queue.')]
class QueueDispatchCommand extends Command
{
    #[Argument('job', mode: InputArgument::REQUIRED, description: 'Job class name')]
    public string $job;

    #[Option('connection', shortcut: 'c', mode: InputOption::VALUE_OPTIONAL, description: 'Queue connection name')]
    public ?string $connection = null;

    #[Option('queue', shortcut: 'Q', mode: InputOption::VALUE_OPTIONAL, description: 'Queue name', default: 'default')]
    public string $queue = 'default';

    #[Option('data', shortcut: 'd', mode: InputOption::VALUE_OPTIONAL, description: 'JSON payload')]
    public ?string $data = null;

    #[Option('delay', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Delay in seconds', default: 0)]
    public int $delay = 0;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists($this->job)) {
            $output->error("Job class not found: {$this->job}");

            return 1;
        }

        $payload = $this->data !== null ? json_decode($this->data, true) ?? [] : [];
        $job = new $this->job(...$payload);

        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($this->connection);
        $connection->add($job, $this->queue, ['delay' => $this->delay]);

        $output->success("Dispatched {$this->job} to queue {$this->queue}.");

        return 0;
    }
}
