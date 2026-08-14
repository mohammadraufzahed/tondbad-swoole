<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_middleware_groups_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('expands named middleware groups on routes', function () {
    $this->route->middlewareGroup('web', ['SessionMiddleware', 'CsrfMiddleware']);
    $this->route->get('/profile', fn () => 'ok')->middleware(['web', 'AuthMiddleware']);

    $routes = $this->route->getRoutes();

    expect($routes[0][3])->toBe(['SessionMiddleware', 'CsrfMiddleware', 'AuthMiddleware']);
});

it('expands named middleware groups in groups', function () {
    $this->route->middlewareGroup('api', ['JsonMiddleware', 'ThrottleMiddleware']);

    $this->route->middleware(['api'])->group(function (Route $route) {
        $route->get('/users', fn () => 'ok');
    });

    $routes = $this->route->getRoutes();

    expect($routes[0][3])->toBe(['JsonMiddleware', 'ThrottleMiddleware']);
});

it('generates urls for named routes', function () {
    $this->route->get('/users/{user}', fn () => 'ok', [], 'users.show');

    expect($this->route->url('users.show', ['user' => 5]))->toBe('/users/5');
});

it('appends extra parameters as query string', function () {
    $this->route->get('/users/{user}', fn () => 'ok', [], 'users.show');

    expect($this->route->url('users.show', ['user' => 5, 'tab' => 'profile']))->toBe('/users/5?tab=profile');
});

it('generates absolute urls', function () {
    $this->app->config->set('app.url_scheme', 'https');
    $this->app->config->set('app.url_host', 'tondbad.dev');

    $this->route = $this->app->container->make(Route::class);
    $this->route->get('/home', fn () => 'ok', [], 'home');

    expect($this->route->url('home', [], false))->toBe('https://tondbad.dev/home');
});
