<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Database\Migrations\MigrationPathManager;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\Providers\Default\DatabaseServiceProvider;

it('collects migration paths from service providers during boot', function () {
    $container = new Container();
    $container->singleton(Env::class, fn () => new Env());
    $container->singleton(Config::class, function () use ($container): Config {
        return new Config($container->make(Env::class), '/tmp');
    });
    $container->singleton(MigrationPathManager::class, fn () => new MigrationPathManager());

    $modulePath = sys_get_temp_dir() . '/tondbad_module_migrations_' . uniqid();

    $container->singleton(App::class, fn () => new class ($modulePath) {
        public function __construct(private readonly string $modulePath)
        {
        }

        public function getProviders(): array
        {
            return [new class ($this->modulePath) extends ServiceProvider {
                public function __construct(private readonly string $path)
                {
                    $this->loadMigrationsFrom($path);
                }
            }];
        }

        public function basePath(string $path = ''): string
        {
            return '/tmp/' . ltrim($path, '/');
        }
    });

    $provider = new DatabaseServiceProvider();
    $provider->register($container);
    $provider->boot($container);

    expect($container->make(MigrationPathManager::class)->getPaths())->toContain($modulePath);
});
