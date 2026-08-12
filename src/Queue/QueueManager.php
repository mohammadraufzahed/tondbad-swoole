<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use Predis\Client as PredisClient;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Queue\Drivers\DatabaseQueue;
use TondbadSwoole\Queue\Drivers\RedisQueue;
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
            'redis' => new RedisQueue(
                new PredisClient($this->redisParameters($config)),
                $config['prefix'] ?? 'tondbad',
                $config['queue'] ?? 'default',
                (int) ($config['retry_after'] ?? 60),
                (int) ($config['block_for'] ?? 1),
            ),
            default => new SyncQueue($this->container->make(Worker::class)),
        };
    }

    protected function getConnectionConfig(string $name): array
    {
        return (array) $this->config->get("queue.connections.{$name}", []);
    }

    private function redisParameters(array $config): array
    {
        $parameters = [
            'scheme' => $config['scheme'] ?? 'tcp',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (int) ($config['port'] ?? 6379),
            'database' => (int) ($config['database'] ?? 0),
        ];

        if (!empty($config['password'])) {
            $parameters['password'] = $config['password'];
        }

        return $parameters;
    }
}
