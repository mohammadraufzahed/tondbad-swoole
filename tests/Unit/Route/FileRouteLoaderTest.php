<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_file_routes_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);
    mkdir("{$this->tmpDir}/routes/http", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");
    file_put_contents("{$this->tmpDir}/config/routes.php", "<?php\nreturn ['http' => 'routes/http.php', 'grpc' => 'routes/grpc.php', 'controllers' => [], 'file_routes' => ['enabled' => true, 'path' => 'routes/http']];");
});

it('loads index.php as the root route', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});

it('loads a file as a route path', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    file_put_contents("{$this->tmpDir}/routes/http/users.php", "<?php\nreturn fn() => 'users';");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users')[0])->toBe(\FastRoute\Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});

it('loads dynamic segments from [id].php', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    file_put_contents("{$this->tmpDir}/routes/http/users.php", "<?php\nreturn ['GET' => fn() => 'users'];");
    mkdir("{$this->tmpDir}/routes/http/users", 0777, true);
    file_put_contents("{$this->tmpDir}/routes/http/users/[id].php", "<?php\nreturn ['GET' => fn(\$r, \$res, int \$id) => 'user'];");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users/5')[0])->toBe(\FastRoute\Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/users/abc')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});

it('loads catch-all segments from [...slug].php', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    mkdir("{$this->tmpDir}/routes/http/docs", 0777, true);
    file_put_contents("{$this->tmpDir}/routes/http/docs/[...slug].php", "<?php\nreturn fn() => 'docs';");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/docs/a/b/c')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});

it('applies _middleware.php to sibling routes', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    file_put_contents("{$this->tmpDir}/routes/http/_middleware.php", "<?php\nreturn ['AuthMiddleware'];");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $routes = $route->getRoutes();

    expect($routes[0][3])->toBe(['AuthMiddleware']);
});

it('loads optional catch-all segments from [[...slug]].php', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    mkdir("{$this->tmpDir}/routes/http/docs", 0777, true);
    file_put_contents("{$this->tmpDir}/routes/http/docs/[[...slug]].php", "<?php\nreturn fn() => 'docs optional';");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/docs')[0])->toBe(\FastRoute\Dispatcher::FOUND);
    expect($dispatcher->dispatch('GET', '/docs/a/b/c')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});

it('ignores route group directories in the url', function () {
    file_put_contents("{$this->tmpDir}/routes/http/index.php", "<?php\nreturn fn() => 'root';");
    mkdir("{$this->tmpDir}/routes/http/(api)", 0777, true);
    file_put_contents("{$this->tmpDir}/routes/http/(api)/users.php", "<?php\nreturn fn() => 'api users';");

    $app = AppFactory::create($this->tmpDir);
    $route = $app->container->make(Route::class);

    $dispatcher = $route->getDispatcher();

    expect($dispatcher->dispatch('GET', '/users')[0])->toBe(\FastRoute\Dispatcher::FOUND);
});
