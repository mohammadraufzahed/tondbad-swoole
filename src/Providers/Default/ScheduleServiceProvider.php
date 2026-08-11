<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use Closure;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Scheduling\Schedule;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Schedule::class, function () use ($container): Schedule {
            $app = $container->make(App::class);

            return new Schedule($container, $app->basePath());
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
}
