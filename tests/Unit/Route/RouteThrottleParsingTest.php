<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Middleware\ThrottleMiddleware;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_throttle_parsing_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('parses throttle:max,window middleware strings', function () {
    $this->route->get('/api/expensive', fn () => 'ok')->middleware(['throttle:5,60']);

    $routes = $this->route->getRoutes();

    expect($routes[0][3])->toHaveCount(1);
    expect($routes[0][3][0])->toBeInstanceOf(ThrottleMiddleware::class);
});

it('uses default throttle limits when no parameters are given', function () {
    $this->route->get('/api/expensive', fn () => 'ok')->middleware(['throttle']);

    $routes = $this->route->getRoutes();

    expect($routes[0][3][0])->toBeInstanceOf(ThrottleMiddleware::class);
});

it('expands throttle middleware inside named groups', function () {
    $this->route->middlewareGroup('api', ['throttle:10,1']);
    $this->route->get('/api/users', fn () => 'ok')->middleware(['api']);

    $routes = $this->route->getRoutes();

    expect($routes[0][3][0])->toBeInstanceOf(ThrottleMiddleware::class);
});
