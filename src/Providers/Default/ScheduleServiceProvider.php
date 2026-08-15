<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Closure;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use Predis\Client as PredisClient;
use TondbadSwoole\Queue\RateLimiter\NullRateLimiter;
use TondbadSwoole\Queue\RateLimiter\RateLimiterInterface;
use TondbadSwoole\Scheduling\Contracts\LockProvider;
use TondbadSwoole\Scheduling\Contracts\ScheduleStore;
use TondbadSwoole\Scheduling\Locks\FileLockProvider;
use TondbadSwoole\Scheduling\Locks\NullLockProvider;
use TondbadSwoole\Scheduling\Schedule;
use TondbadSwoole\Scheduling\ScheduleRegistry;
use TondbadSwoole\Scheduling\Scheduler;
use TondbadSwoole\Scheduling\Stores\MemoryScheduleStore;
use TondbadSwoole\Scheduling\Stores\RedisScheduleStore;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(ScheduleRegistry::class, static fn () => new ScheduleRegistry());

        $container->singleton(ScheduleStore::class, function () use ($container) {
            $config = $container->make(Config::class);
            $driver = $config->get('schedule.store', 'memory');

            return match ($driver) {
                'database' => $container->make(Stores\DatabaseScheduleStore::class),
                'redis' => new RedisScheduleStore(
                    $config,
                    $container->make(ScheduleRegistry::class),
                    new PredisClient($this->redisParameters($config->get('cache.redis', []))),
                ),
                default => new MemoryScheduleStore(),
            };
        });

        $container->singleton(LockProvider::class, function () use ($container) {
            $config = $container->make(Config::class);

            if ($config->get('schedule.locks', 'file') === 'null') {
                return new NullLockProvider();
            }

            $app = $container->make(App::class);

            return new FileLockProvider($app->basePath(), $config);
        });

        $container->singleton(Scheduler::class, function () use ($container) {
            $app = $container->make(App::class);
            $dispatcher = null;

            try {
                $dispatcher = $container->make(EventDispatcher::class);
            } catch (\Throwable) {
            }

            $rateLimiter = null;

            try {
                $rateLimiter = $container->make(RateLimiterInterface::class);
            } catch (\Throwable) {
            }

            return new Scheduler(
                $container->make(ScheduleStore::class),
                $container->make(ScheduleRegistry::class),
                $container,
                $app->basePath(),
                $container->make(LockProvider::class),
                $dispatcher,
                $rateLimiter ?? new NullRateLimiter(),
            );
        });

        $container->singleton(Schedule::class, function () use ($container) {
            $app = $container->make(App::class);

            return new Schedule(
                $container->make(Scheduler::class),
                $container,
                $app->basePath(),
                $container->make(ScheduleRegistry::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $app = $container->make(App::class);
        $schedule = $container->make(Schedule::class);
        $consoleRoutes = $app->basePath('/routes/console.php');

        if (file_exists($consoleRoutes)) {
            $closure = require $consoleRoutes;

            if ($closure instanceof Closure) {
                $closure($schedule);
            }
        }
    }

    /**
     * @param array<string, mixed> $redisConfig
     *
     * @return array<string, mixed>
     */
    private function redisParameters(array $redisConfig): array
    {
        $parameters = [
            'scheme' => $redisConfig['scheme'] ?? 'tcp',
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => (int) ($redisConfig['port'] ?? 6379),
            'database' => (int) ($redisConfig['database'] ?? 0),
        ];

        if (!empty($redisConfig['password'])) {
            $parameters['password'] = $redisConfig['password'];
        }

        return $parameters;
    }
}
