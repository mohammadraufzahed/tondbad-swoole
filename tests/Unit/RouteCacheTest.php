<?php

declare(strict_types=1);

use TondbadSwoole\Core\Route\RouteRegistrar;

it('compiles a route cache file', function () {
    $cacheFile = $this->tempDir('tondbad_route_cache') . '/routes.cache.php';

    $registrar = new RouteRegistrar($cacheFile);
    $registrar->addRoute('GET', '/hello', fn () => 'hello');
    $registrar->getDispatcher();

    expect($cacheFile)->toBeFile();
});

it('gives cached routes precedence over new routes', function () {
    $cacheFile = $this->tempDir('tondbad_route_cache') . '/routes.cache.php';

    $first = new RouteRegistrar($cacheFile);
    $first->addRoute('GET', '/first', fn () => 'first');
    $first->getDispatcher();

    $second = new RouteRegistrar($cacheFile);
    $second->addRoute('GET', '/second', fn () => 'second');
    $dispatcher = $second->getDispatcher();

    $result = $dispatcher->dispatch('GET', '/first');
    expect($result[0])->toBe(\FastRoute\Dispatcher::FOUND);

    $missing = $dispatcher->dispatch('GET', '/second');
    expect($missing[0])->toBe(\FastRoute\Dispatcher::NOT_FOUND);
});

it('rebuilds the cache when warmCache is called', function () {
    $cacheFile = $this->tempDir('tondbad_route_cache_rebuild') . '/routes.cache.php';

    $first = new RouteRegistrar($cacheFile);
    $first->addRoute('GET', '/first', fn () => 'first');
    $first->getDispatcher();

    $second = new RouteRegistrar($cacheFile);
    $second->addRoute('GET', '/second', fn () => 'second');
    $second->warmCache();

    $dispatcher = $second->getDispatcher();

    expect($dispatcher->dispatch('GET', '/second')[0])->toBe(\FastRoute\Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/missing')[0])->toBe(\FastRoute\Dispatcher::NOT_FOUND);
});
