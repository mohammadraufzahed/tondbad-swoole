<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use FastRoute\Dispatcher;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_fallback_redirect_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('adds a fallback route', function () {
    $this->route->get('/users', fn () => 'users');
    $this->route->fallback(fn () => 'fallback');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users')[0])->toBe(Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/missing')[0])->toBe(Dispatcher::FOUND);
});

it('registers a redirect route', function () {
    $this->route->redirect('/old', '/new', 301);

    $routes = $this->route->getRoutes();

    expect($routes[0][0])->toBe('GET|HEAD');
    expect($routes[0][1])->toBe('/old');
});
