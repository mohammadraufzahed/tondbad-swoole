<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Contracts\CacheContract;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\View\ComponentRegistry;
use TondbadSwoole\View\Live\LiveComponentController;
use TondbadSwoole\View\Live\StateStore;
use TondbadSwoole\View\ViewManager;

final class ViewServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(ViewManager::class, function () use ($container) {
            $config = $container->make(Config::class);

            return new ViewManager(
                config: $config,
                paths: (array) $config->get('view.paths', [base_path() . '/resources/views']),
                compiledPath: (string) $config->get('view.compiled', base_path() . '/storage/cache/views'),
                componentPaths: (array) $config->get('view.component_paths', [base_path() . '/app/View/Components']),
            );
        });

        $container->singleton(StateStore::class, function () use ($container) {
            $cache = $container->has(CacheContract::class) ? $container->make(CacheContract::class) : null;

            return new StateStore($cache ?? new HybridStore(new InMemoryCache()));
        });

        $container->singleton(LiveComponentController::class, function () use ($container) {
            return new LiveComponentController(
                $container->make(ViewManager::class),
                $container->make(StateStore::class),
                $container->make(ViewManager::class)->registry(),
            );
        });
    }

    public function boot(Container $container): void
    {
        if (!$container->has(Route::class)) {
            return;
        }

        /** @var Route $route */
        $route = $container->make(Route::class);

        if ((bool) $container->make(Config::class)->get('view.live.enabled', false)) {
            $route->post('/_live/{component}', [LiveComponentController::class, 'handle']);
        }
    }
}
