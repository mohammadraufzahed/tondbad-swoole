<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use FastRoute\Dispatcher;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_fallback_cache_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");
});

it('matches the fallback route for unknown paths', function () {
    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $route->get('/known', fn () => 'ok');
    $route->fallback(fn () => 'fallback');

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/known')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/unknown')[0])->toBe(Dispatcher::FOUND);
    expect($route->getHandler($dispatcher->dispatch('GET', '/unknown')[1]))->toBeCallable();
});

it('stores the fallback handler so route caching works', function () {
    $cacheFile = "{$this->tmpDir}/storage/cache/routes.cache.php";
    file_put_contents("{$this->tmpDir}/config/app.php", sprintf("<?php\nreturn ['type' => 'http', 'route_cache_file' => '%s'];", $cacheFile));

    $firstApp = AppFactory::create($this->tmpDir);
    $firstRoute = $firstApp->container->make(Route::class);
    $firstRoute->get('/known', fn () => 'ok');
    $firstRoute->fallback(fn () => 'fallback');
    $firstRoute->warmRouteCache();

    $secondApp = AppFactory::create($this->tmpDir);
    $secondRoute = $secondApp->container->make(Route::class);
    $secondRoute->get('/known', fn () => 'ok');
    $secondRoute->fallback(fn () => 'fallback');

    $dispatcher = $secondRoute->getDispatcher();

    expect($dispatcher->dispatch('GET', '/unknown')[0])->toBe(Dispatcher::FOUND);
    expect($secondRoute->getHandler($dispatcher->dispatch('GET', '/unknown')[1]))->toBeCallable();
});
