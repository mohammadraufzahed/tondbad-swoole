<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\Dispatcher;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(EventDispatcher::class, function () use ($container): EventDispatcher {
            return new Dispatcher($container);
        });

        $container->singleton(Dispatcher::class, function () use ($container): Dispatcher {
            return $container->make(EventDispatcher::class);
        });
    }

    public function boot(Container $container): void
    {
        $app = $container->make(App::class);
        $config = $container->make(Config::class);
        $dispatcher = $container->make(EventDispatcher::class);
        $listenersDir = $app->basePath($config->get('app.paths.listeners', 'app/Listeners'));

        if (!is_dir($listenersDir)) {
            return;
        }

        foreach (glob($listenersDir . '/*.php') ?: [] as $file) {
            $class = $this->classNameFromFile($file, $config->get('app.namespaces.listeners', 'App\\Listeners\\'));

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $dispatcher->subscribe($class);
        }
    }

    private function classNameFromFile(string $file, string $namespace): ?string
    {
        $name = basename($file, '.php');

        return $namespace . $name;
    }
}
