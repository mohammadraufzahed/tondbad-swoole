<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_group_builder_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('groups routes under a prefix', function () {
    $this->route->prefix('api/v1')->group(function (Route $route) {
        $route->get('/users', fn () => 'ok', [], 'users.index');
    });

    expect($this->route->url('users.index'))->toBe('/api/v1/users');
});

it('applies middleware to grouped routes', function () {
    $this->route->middleware(['AuthMiddleware'])->group(function (Route $route) {
        $route->get('/admin', fn () => 'ok');
    });

    $routes = $this->route->getRoutes();

    expect($routes[0][3] ?? [])->toContain('AuthMiddleware');
});

it('applies name prefix to grouped routes', function () {
    $this->route->name('api.')->group(function (Route $route) {
        $route->get('/users', fn () => 'ok', [], 'users.index');
    });

    expect($this->route->has('api.users.index'))->toBeTrue();
    expect($this->route->url('api.users.index'))->toBe('/users');
});

it('combines prefix middleware and name in a fluent group', function () {
    $this->route
        ->prefix('api/v2')
        ->middleware(['ApiMiddleware'])
        ->name('api.v2.')
        ->where('id', '[0-9]+')
        ->group(function (Route $route) {
            $route->get('/users/{id}', fn () => 'ok', [], 'users.show');
        });

    expect($this->route->url('api.v2.users.show', ['id' => 5]))->toBe('/api/v2/users/5');

    $dispatcher = $this->route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/api/v2/users/5')[0])->toBe(\FastRoute\Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/api/v2/users/abc')[0])->toBe(\FastRoute\Dispatcher::NOT_FOUND);
});

it('applies nested groups', function () {
    $this->route->prefix('api')->group(function (Route $route) {
        $route->prefix('v1')->group(function (Route $route) {
            $route->get('/users', fn () => 'ok', [], 'users.index');
        });
    });

    expect($this->route->url('users.index'))->toBe('/api/v1/users');
});

it('applies namespace to grouped controllers', function () {
    $this->route->namespace('App\\Http\\Controllers')->group(function (Route $route) {
        $route->get('/users', ['UserController', 'index']);
    });

    $routes = $this->route->getRoutes();

    expect($routes[0][2])->toBe(['App\\Http\\Controllers\\UserController', 'index']);
});
