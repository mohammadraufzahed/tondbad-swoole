<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Queue\Drivers\DatabaseQueue;
use TondbadSwoole\Queue\Drivers\SyncQueue;

class QueueManager
{
    protected array $queues = [];

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly DatabaseManager $databaseManager,
    ) {
    }

    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->getDefaultDriver();

        if (!isset($this->queues[$name])) {
            $this->queues[$name] = $this->createConnection($name);
        }

        return $this->queues[$name];
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('queue.default', 'sync');
    }

    protected function createConnection(string $name): QueueInterface
    {
        $config = $this->getConnectionConfig($name);

        return match ($config['driver'] ?? 'sync') {
            'database' => new DatabaseQueue(
                $this->databaseManager->connection($config['connection'] ?? null),
                $config['table'] ?? 'jobs',
                $config['queue'] ?? 'default',
                (int) ($config['retry_after'] ?? 60),
                $this->config->get('queue.failed.table', 'failed_jobs'),
                $config['pause_table'] ?? 'queue_pauses',
            ),
            default => new SyncQueue($this->container->make(Worker::class)),
        };
    }

    protected function getConnectionConfig(string $name): array
    {
        return (array) $this->config->get("queue.connections.{$name}", []);
    }
}
