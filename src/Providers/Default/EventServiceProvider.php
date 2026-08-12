<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Dispatcher;
use TondbadSwoole\Events\Listener as ListenerAttribute;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Queue\QueueInterface;

class EventServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Dispatcher::class, function () use ($container): Dispatcher {
            return new Dispatcher(
                $container,
                $container->has(QueueInterface::class) ? $container->make(QueueInterface::class) : null,
            );
        });
    }

    public function boot(Container $container): void
    {
        $app = $container->make(App::class);
        $config = $container->make(Config::class);
        $dispatcher = $container->make(Dispatcher::class);
        $listenersDir = $app->basePath($config->get('app.paths.listeners', 'app/Listeners'));

        if (!is_dir($listenersDir)) {
            return;
        }

        foreach (glob($listenersDir . '/*.php') ?: [] as $file) {
            $class = $this->classNameFromFile($file, $config->get('app.namespaces.listeners', 'App\\Listeners\\'));

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(ListenerAttribute::class);

            if (count($attributes) === 0) {
                continue;
            }

            /** @var ListenerAttribute $attribute */
            $attribute = $attributes[0]->newInstance();

            foreach ($attribute->events as $event) {
                $dispatcher->listen($event, $class);
            }
        }
    }

    private function classNameFromFile(string $file, string $namespace): ?string
    {
        $name = basename($file, '.php');

        return $namespace . $name;
    }
}
