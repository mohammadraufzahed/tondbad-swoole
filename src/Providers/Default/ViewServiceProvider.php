<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;
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
    }
}
