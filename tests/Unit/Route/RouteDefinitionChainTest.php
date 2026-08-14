<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_definition_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('chains constraints, middleware, and name on a route definition', function () {
    $this->route
        ->middlewareGroup('api', ['JsonMiddleware'])
        ->get('/users/{id}', fn () => 'ok')
        ->whereNumber('id')
        ->middleware(['api', 'AuthMiddleware'])
        ->name('users.show');

    expect($this->route->has('users.show'))->toBeTrue();
    expect($this->route->url('users.show', ['id' => 7]))->toBe('/users/7');

    $routes = $this->route->getRoutes();

    expect($routes[0][3])->toBe(['JsonMiddleware', 'AuthMiddleware']);
});
