<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Contracts\CacheContract;
use TondbadSwoole\Core\Cache\HybridStore;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Middleware\Authenticate;
use TondbadSwoole\Http\Middleware\VerifyCsrfToken;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
use TondbadSwoole\View\ComponentRegistry;
use TondbadSwoole\View\Live\LiveComponentController;
use TondbadSwoole\View\Live\LiveComponentManager;
use TondbadSwoole\View\Live\LiveSseController;
use TondbadSwoole\View\Live\SseConnectionManager;
use TondbadSwoole\View\Live\StateStore;
use TondbadSwoole\View\Live\WsConnectionManager;
use TondbadSwoole\View\ViewManager;

final class ViewServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $config = $container->make(Config::class);

        $container->singleton(ComponentRegistry::class, function () use ($config) {
            $registry = new ComponentRegistry();
            $registry->discover((array) $config->get('view.component_paths', [base_path() . '/app/View/Components']));

            return $registry;
        });

        $container->singleton(ViewManager::class, function () use ($container, $config) {
            $manager = new ViewManager(
                config: $config,
                paths: (array) $config->get('view.paths', [base_path() . '/resources/views']),
                compiledPath: (string) $config->get('view.compiled', base_path() . '/storage/cache/views'),
                componentPaths: (array) $config->get('view.component_paths', [base_path() . '/app/View/Components']),
                registry: $container->make(ComponentRegistry::class),
            );

            $manager->setLiveComponentManager($container->make(LiveComponentManager::class));

            return $manager;
        });

        $container->singleton(StateStore::class, function () use ($container) {
            $cache = $container->has(CacheContract::class) ? $container->make(CacheContract::class) : null;

            return new StateStore($cache ?? new HybridStore(new InMemoryCache()));
        });

        $container->singleton(LiveComponentManager::class, function () use ($container) {
            return new LiveComponentManager(
                $container->make(StateStore::class),
                $container->make(ComponentRegistry::class),
            );
        });

        $container->singleton(LiveComponentController::class, function () use ($container) {
            return new LiveComponentController(
                $container->make(LiveComponentManager::class),
                $container->make(SseConnectionManager::class),
            );
        });

        $container->singleton(WsConnectionManager::class, function () use ($container) {
            return new WsConnectionManager($container->make(LiveComponentManager::class));
        });

        $container->singleton(SseConnectionManager::class, fn () => new SseConnectionManager());

        $container->singleton(LiveSseController::class, function () use ($container) {
            return new LiveSseController($container->make(SseConnectionManager::class));
        });
    }

    public function boot(Container $container): void
    {
        if (!$container->has(Route::class)) {
            return;
        }

        /** @var Route $route */
        $route = $container->make(Route::class);

        $route->get('/tondview.js', fn(Request $request, Response $response) => $response->file(base_path() . '/public/tondview.js', 'application/javascript'));

        if ((bool) $container->make(Config::class)->get('view.live.enabled', false)) {
            $route->post('/_live/{component}', [LiveComponentController::class, 'handle'], [Authenticate::class, VerifyCsrfToken::class]);
            $route->get('/_live/sse', [LiveSseController::class, 'handle']);
        }
    }
}
