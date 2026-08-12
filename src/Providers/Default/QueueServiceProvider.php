<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Queue\Drivers\DatabaseQueue;
use TondbadSwoole\Queue\Failed\DatabaseFailedJobProvider;
use TondbadSwoole\Queue\Failed\FailedJobProviderInterface;
use TondbadSwoole\Queue\FlowProducer;
use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\RateLimiter\DatabaseRateLimiter;
use TondbadSwoole\Queue\RateLimiter\NullRateLimiter;
use TondbadSwoole\Queue\RateLimiter\RateLimiterInterface;
use TondbadSwoole\Queue\Worker;

class QueueServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations/Queue');

        $container->singleton(QueueManager::class, function () use ($container): QueueManager {
            return new QueueManager(
                $container->make(Config::class),
                $container,
                $container->make(DatabaseManager::class),
            );
        });

        $container->singleton(QueueInterface::class, function () use ($container): QueueInterface {
            return $container->make(QueueManager::class)->connection();
        });

        $container->singleton(Worker::class, function () use ($container): Worker {
            return new Worker(
                $container,
                $container->make(FailedJobProviderInterface::class),
            );
        });

        $container->singleton(FlowProducer::class, function () use ($container): FlowProducer {
            return new FlowProducer($container->make(QueueManager::class));
        });

        $container->singleton(FailedJobProviderInterface::class, function () use ($container): FailedJobProviderInterface {
            $config = $container->make(Config::class);
            $connection = $container->make(DatabaseManager::class)->connection(
                $config->get('queue.failed.database', null)
            );

            return new DatabaseFailedJobProvider(
                $connection,
                (string) $config->get('queue.failed.table', 'failed_jobs'),
            );
        });

        $container->singleton(RateLimiterInterface::class, function () use ($container): RateLimiterInterface {
            $config = $container->make(Config::class);
            $driver = $config->get('queue.rateLimiter.driver', null);

            if ($driver === 'database') {
                $connection = $container->make(DatabaseManager::class)->connection(
                    $config->get('queue.rateLimiter.database', null)
                );

                return new DatabaseRateLimiter(
                    $connection,
                    (string) $config->get('queue.rateLimiter.table', 'rate_limits'),
                );
            }

            return new NullRateLimiter();
        });
    }
}
