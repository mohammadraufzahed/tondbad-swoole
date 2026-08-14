<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use FastRoute\Dispatcher;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_constraints_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('applies per-route parameter constraints', function () {
    $this->route->get('/users/{id}', fn () => 'ok')->where('id', '[0-9]+');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users/42')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/users/abc')[0])->toBe(Dispatcher::NOT_FOUND);
});

it('applies whereNumber helper', function () {
    $this->route->get('/orders/{order}', fn () => 'ok')->whereNumber('order');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/orders/123')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/orders/abc')[0])->toBe(Dispatcher::NOT_FOUND);
});

it('applies whereUuid helper', function () {
    $this->route->get('/files/{id}', fn () => 'ok')->whereUuid('id');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/files/550e8400-e29b-41d4-a716-446655440000')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/files/invalid')[0])->toBe(Dispatcher::NOT_FOUND);
});

it('applies global patterns', function () {
    $this->route->pattern('id', '[0-9]+');
    $this->route->get('/users/{id}', fn () => 'ok');
    $this->route->get('/posts/{id}', fn () => 'ok');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users/99')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/users/abc')[0])->toBe(Dispatcher::NOT_FOUND);
    expect($dispatcher->dispatch('GET', '/posts/99')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/posts/abc')[0])->toBe(Dispatcher::NOT_FOUND);
});

it('allows route specific constraints to override global patterns', function () {
    $this->route->pattern('id', '[0-9]+');
    $this->route->get('/users/{id}', fn () => 'ok')->where('id', '[a-z]+');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users/abc')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/users/123')[0])->toBe(Dispatcher::NOT_FOUND);
});
